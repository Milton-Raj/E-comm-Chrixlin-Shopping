import { expect, test } from "@playwright/test";
import { expectNoA11yViolations } from "../support";

test("home shows the collection and opens a product", async ({ page }) => {
  await page.goto("/");
  await expect(page.getByRole("heading", { name: "New arrivals" })).toBeVisible();
  await expectNoA11yViolations(page);

  await page.getByRole("link", { name: "Meridian 38 Watch" }).first().click();
  await expect(page).toHaveURL(/\/product\/meridian-38-watch$/);
  await expect(page.getByRole("heading", { level: 1, name: "Meridian 38 Watch" })).toBeVisible();
  await expect(page.getByText("₹32,000.00").first()).toBeVisible();
  await expect(page.getByText("Swiss quartz").first()).toBeVisible();

  await page.getByRole("button", { name: "Black leather" }).click();
  await expect(page.getByRole("button", { name: "Black leather" })).toHaveAttribute("aria-pressed", "true");
  await page.getByRole("button", { name: "Add to bag" }).click();
  const drawer = page.getByRole("dialog", { name: "Your bag" });
  await expect(drawer).toBeVisible();
  await expect(drawer.getByText("Black leather")).toBeVisible();
  await page.keyboard.press("Escape");
  await expectNoA11yViolations(page);
});

test("shop filters and sorts without a full reload", async ({ page }) => {
  await page.goto("/shop");
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow, "shop must not scroll horizontally").toBeLessThanOrEqual(0);

  await page.getByRole("button", { name: "Digital Editions" }).click();
  await expect(page).toHaveURL(/category=digital/);
  await expect(page.getByText("5 pieces")).toBeVisible();

  await page.getByLabel("Sort by").selectOption("price_asc");
  await expect(page).toHaveURL(/sort=price_asc/);
  await expect(page.getByRole("heading", { level: 3 }).first()).toHaveText("Collected Essays on Craft");
});

test("digital products show download details and live under /digital", async ({ page }) => {
  await page.goto("/digital/the-art-of-slow-living");
  await expect(page.getByText("EPUB + PDF")).toBeVisible();
  await expect(page.getByText("Up to 5 downloads")).toBeVisible();

  // The redirect runs inside a streamed boundary, so Next.js performs it client-side to the canonical URL.
  await page.goto("/product/the-art-of-slow-living");
  await expect(page).toHaveURL(/\/digital\/the-art-of-slow-living$/);
});

test("search finds products", async ({ page }) => {
  await page.goto("/search");
  await page.getByRole("searchbox", { name: "Search the collection" }).fill("leather");
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(/q=leather/);
  await expect(page.getByRole("link", { name: "Voyager Leather Weekender" })).toBeVisible();
});
