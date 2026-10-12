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

// A page cached by the CDN before a release names script files that no longer exist. Instead of
// a 404 (which leaves the page blank), answer with a one-line script that reloads the page at a
// fresh address the CDN has not cached. ?__fresh stops it looping; the app removes it afterwards.
const staticDir = path.join(__dirname, ".next", "static");
const reloadScript = '(function(){try{var u=new URL(location.href);if(u.searchParams.has("__fresh"))return;u.searchParams.set("__fresh",Date.now());location.replace(u.toString())}catch(e){}})();';
const createServer = http.createServer;
http.createServer = function (...args) {
  const i = args.findIndex((a) => typeof a === "function");
  if (i !== -1) {
    const handler = args[i];
    args[i] = function (req, res) {
      const m = req.method === "GET" && req.url && req.url.match(/^\/_next\/static\/chunks\/([A-Za-z0-9_~.-]+\.js)$/);
      if (m && !m[1].startsWith(".") && !fs.existsSync(path.join(staticDir, "chunks", m[1]))) {
        res.writeHead(200, { "Content-Type": "application/javascript; charset=utf-8", "Cache-Control": "no-store", "CDN-Cache-Control": "no-store" });
        return res.end(reloadScript);
      }
      return handler.call(this, req, res);
    };
  }
  return createServer.apply(this, args);
};

require("./server.js");
