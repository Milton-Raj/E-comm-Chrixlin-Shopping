import { expect, test } from "@playwright/test";
import { expectNoA11yViolations } from "../support";

/** Every link in the site chrome must resolve — no navigation may land on a 404. */
test("all header, footer and bottom-nav links resolve", async ({ page, request }) => {
  await page.goto("/");
  const hrefs = await page
    .locator("header a[href^='/'], footer a[href^='/'], nav a[href^='/']")
    .evaluateAll((links) => [...new Set(links.map((a) => a.getAttribute("href")!))]);

  expect(hrefs.length).toBeGreaterThan(10);
  for (const href of hrefs) {
    const response = await request.get(href);
    expect(response.status(), `${href} returned ${response.status()}`).toBe(200);
  }
});

test("placeholder pages are accessible and explain what's coming", async ({ page }) => {
  for (const [path, heading] of [
    ["/cart", "Your bag"],
    ["/shop", "Shop"],
    ["/pages/privacy", "Privacy policy"],
  ] as const) {
    await page.goto(path);
    await expect(page.getByRole("heading", { level: 1, name: heading })).toBeVisible();
    await expectNoA11yViolations(page);
  }
});

test("unknown content pages show the 404 state and are not indexed", async ({ page }) => {
  // Runtime (non-prerendered) params stream, so Next.js signals not-found via UI + noindex rather than status.
  await page.goto("/pages/not-a-real-page");
  await expect(page.getByRole("heading", { name: "Page not found" })).toBeVisible();
  await expect(page.locator('meta[name="robots"][content*="noindex"]').first()).toBeAttached();
});

test("no page scrolls horizontally", async ({ page }) => {
  for (const path of ["/", "/shop", "/categories", "/category/home", "/product/meridian-38-watch", "/digital/analog-film-presets", "/search?q=leather", "/login"]) {
    await page.goto(path);
    await page.waitForLoadState("networkidle");
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${path} overflows by ${overflow}px`).toBeLessThanOrEqual(0);
  }
});
