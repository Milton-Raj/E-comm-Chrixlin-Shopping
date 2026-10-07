import { execFileSync } from "node:child_process";
import { homedir } from "node:os";
import path from "node:path";

/** Reset the database so every run starts from the seeded demo data (dev only). */
export default async function globalSetup() {
  if (process.env.E2E_SKIP_DB_RESET) return;

  const backend = path.resolve(import.meta.dirname, "../../backend");
  execFileSync("php", ["artisan", "migrate:fresh", "--seed", "--force"], {
    cwd: backend,
    stdio: "inherit",
    env: { ...process.env, PATH: `${path.join(homedir(), ".config/herd-lite/bin")}:${process.env.PATH ?? ""}` },
  });

  // Reseeding changes ids, so drop the storefront's cached catalog (same hook the API uses after admin edits).
  const baseURL = process.env.E2E_BASE_URL ?? "http://localhost:3000";
  await fetch(`${baseURL}/api/revalidate`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-Revalidate-Secret": process.env.REVALIDATE_SECRET ?? "local-revalidate-secret" },
    body: JSON.stringify({ tags: ["catalog", "content"] }),
  }).catch(() => undefined);
}
