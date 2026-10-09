import { fileURLToPath } from "node:url";
import { expect, test } from "@playwright/test";
import { DEMO_PASSWORD, expectNoA11yViolations, signIn } from "../support";

test("homepage hero slideshow can be paused and navigated", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "tablet", "Covered by the phone and desktop layouts.");

  await page.goto("/");
  const hero = page.getByRole("region", { name: "Featured collections" });
  await expect(hero.getByRole("heading", { level: 2, name: "Light that lingers." })).toBeVisible();
  await page.waitForTimeout(1800);
  await page.screenshot({ path: testInfo.outputPath("hero-1.png") });

  await hero.getByRole("button", { name: "Pause slideshow" }).click();
  await expect(hero.getByRole("button", { name: "Play slideshow" })).toBeVisible();
  await hero.getByRole("button", { name: "Show slide 2" }).click();
  await expect(hero.getByRole("heading", { level: 2, name: "Quiet objects, made to keep." })).toBeVisible();
  await expect(hero.getByRole("link", { name: "Shop Jesmonite" })).toHaveAttribute("href", "/search?q=jesmonite");
  await page.waitForTimeout(1800);
  await page.screenshot({ path: testInfo.outputPath("hero-2.png") });

  await expectNoA11yViolations(page);
});

test("content editors manage hero slides", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Admin journeys run on desktop.");

  await signIn(page, "admin@example.test", DEMO_PASSWORD, "/admin/content");
  await expect(page.getByText("No custom slides yet.")).toBeVisible();

  await page.getByRole("button", { name: "Add slide" }).click();
  await page.locator("#hs-image").setInputFiles(fileURLToPath(new URL("../../../frontend/public/hero/arch-candle.jpg", import.meta.url)));
  await page.getByLabel(/^Headline/).fill("Autumn edit");
  await page.getByLabel("Button text").fill("Shop the edit");
  await page.getByLabel("Button link").fill("/shop");
  await page.screenshot({ path: testInfo.outputPath("hero-admin.png"), fullPage: true });
  await page.getByRole("button", { name: "Save slide" }).click();

  await expect(page.getByText("Button: Shop the edit → /shop")).toBeVisible();
  await expectNoA11yViolations(page);

  await page.getByRole("button", { name: "Delete “Autumn edit”" }).click();
  await page.getByRole("button", { name: "Delete", exact: true }).click();
  await expect(page.getByText("No custom slides yet.")).toBeVisible();
});
