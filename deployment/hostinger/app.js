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
require("./server.js");
