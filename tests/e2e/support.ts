import AxeBuilder from "@axe-core/playwright";
import { expect, type Page } from "@playwright/test";
import { createHmac } from "node:crypto";

/** Password for DemoSeeder accounts; matches DEMO_PASSWORD in backend/.env (local only). */
export const DEMO_PASSWORD = process.env.E2E_DEMO_PASSWORD ?? "password123";

export function uniqueEmail(prefix: string): string {
  return `${prefix}-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.test`;
}

/** Fails on serious/critical WCAG 2.2 AA violations (TESTING.md §1). */
export async function expectNoA11yViolations(page: Page) {
  // Scroll through the page like a shopper so scroll-reveal content is shown, then let
  // entrance animations settle: contrast is only meaningful at the final colours.
  // Endless decorative loops (ribbon, scroll cue) never finish, so they are skipped.
  await page.evaluate(async () => {
    const y = window.scrollY;
    for (let top = 0; top < document.body.scrollHeight; top += window.innerHeight * 0.8) {
      window.scrollTo(0, top);
      await new Promise((r) => setTimeout(r, 60));
    }
    window.scrollTo(0, y);
  });
  await page.waitForTimeout(100);
  await page.evaluate(() =>
    Promise.all(
      document.getAnimations()
        .filter((a) => a.effect?.getComputedTiming().iterations !== Infinity)
        .map((a) => a.finished.catch(() => undefined)),
    ),
  );
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
  const serious = results.violations.filter((v) => v.impact === "serious" || v.impact === "critical");
  expect(serious.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

/** RFC 6238 TOTP (SHA-1, 6 digits, 30s) so tests can complete real 2FA setup. */
export function totp(base32Secret: string, now = Date.now()): string {
  const alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
  let bits = "";
  for (const char of base32Secret.replace(/=+$/, "").toUpperCase()) {
    bits += alphabet.indexOf(char).toString(2).padStart(5, "0");
  }
  const key = Buffer.from(bits.match(/.{8}/g)!.map((b) => parseInt(b, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(now / 1000 / 30)));
  const hmac = createHmac("sha1", key).update(counter).digest();
  const offset = hmac[hmac.length - 1]! & 0x0f;
  const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;
  return code.toString().padStart(6, "0");
}

export async function signIn(page: Page, email: string, password: string, next = "/account") {
  await page.goto(`/login?next=${encodeURIComponent(next)}`);
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
}
