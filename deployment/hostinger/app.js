// Entry point for Hostinger's Node runner (LiteSpeed/Passenger). Loads server-only
// settings from runtime.env (kept on the server, never in git), then starts Next.js.
const fs = require("fs");
const path = require("path");
const file = path.join(__dirname, "runtime.env");
if (fs.existsSync(file)) {
  for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (m && !line.trim().startsWith("#")) process.env[m[1]] = m[2].replace(/^"(.*)"$/, "$1");
  }
}
process.env.NODE_ENV = "production";
process.env.HOSTNAME = "127.0.0.1"; // the shell's HOSTNAME is the machine name; bind locally behind the web server

// Admin pages must never sit in Hostinger's CDN: a cached copy outlives the release whose
// script files it names and then loads blank. Next.js sets Cache-Control itself, so it is
// replaced here as the response goes out.
const http = require("http");
const writeHead = http.ServerResponse.prototype.writeHead;
http.ServerResponse.prototype.writeHead = function (...args) {
  const url = this.req && this.req.url;
  if (url && /^\/admin(\/|\?|$)/.test(url)) {
    this.setHeader("Cache-Control", "private, no-store, max-age=0");
    this.setHeader("CDN-Cache-Control", "no-store");
    for (const h of args) {
      if (h && typeof h === "object" && !Array.isArray(h)) {
        for (const k of Object.keys(h)) if (k.toLowerCase() === "cache-control") delete h[k];
      }
    }
  }
  return writeHead.apply(this, args);
};

require("./server.js");
