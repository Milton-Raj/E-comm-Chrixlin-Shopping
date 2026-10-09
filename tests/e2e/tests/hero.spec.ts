import { fileURLToPath } from "node:url";
import { expect, test } from "@playwright/test";
import { DEMO_PASSWORD, expectNoA11yViolations, signIn } from "../support";

test("homepage hero slideshow can be paused and navigated", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "tablet", "Covered by the phone and desktop layouts.");

  await page.goto("/");
  const hero = page.getByRole("region", { name: "Featured collections" });
  await expect(hero.getByRole("heading", { level: 2, name: "Good enough to eat. Made to light." })).toBeVisible();
  await page.waitForTimeout(1800);
  await page.screenshot({ path: testInfo.outputPath("hero-1.png") });

  // Advances on its own even with the mouse resting over it (desktop regression).
  const box = await hero.boundingBox();
  if (box) await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await expect(hero.getByRole("heading", { level: 2, name: "Indulgence, poured by hand." })).toBeVisible({ timeout: 9000 });

  await hero.getByRole("button", { name: "Pause slideshow" }).click();
  await expect(hero.getByRole("button", { name: "Play slideshow" })).toBeVisible();
  await hero.getByRole("button", { name: "Show slide 2" }).click();
  await page.waitForTimeout(7000);
  await expect(hero.getByRole("heading", { level: 2, name: "Indulgence, poured by hand." })).toBeVisible();
  await expect(hero.getByRole("link", { name: "Shop the collection" })).toHaveAttribute("href", "/shop");
  await page.waitForTimeout(1800);
  await page.screenshot({ path: testInfo.outputPath("hero-2.png") });

  await expectNoA11yViolations(page);
});

test("content editors manage hero slides and site text", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Admin journeys run on desktop.");

  await signIn(page, "admin@example.test", DEMO_PASSWORD, "/admin/content");
  // The launch slides are in the database, so the owner sees and edits what the store shows.
  await expect(page.getByText("Good enough to eat. Made to light.")).toBeVisible();

  await page.getByRole("button", { name: "Add slide" }).click();
  await page.locator("#hs-image").setInputFiles(fileURLToPath(new URL("../../../frontend/public/hero/arch-candle.jpg", import.meta.url)));
  await page.getByLabel(/^Headline/).fill("Autumn edit");
  await page.getByLabel("Button text").fill("Shop the edit");
  await page.getByLabel("Button link").fill("/shop");
  await page.getByRole("button", { name: "Save slide" }).click();
  await expect(page.getByText("Button: Shop the edit → /shop")).toBeVisible();
  await page.getByRole("button", { name: "Delete “Autumn edit”" }).click();
  await page.getByRole("button", { name: "Delete", exact: true }).click();
  await expect(page.getByText("Button: Shop the edit → /shop")).toHaveCount(0);

  await page.getByRole("tab", { name: "Site text" }).click();
  const announcement = page.getByLabel("Main message");
  await expect(announcement).toHaveValue("Indulgence, poured by hand");
  await announcement.fill("Hand-poured dessert candles");
  await page.getByRole("button", { name: "Save 1 change" }).click();
  await expect(page.getByText("Site text saved.")).toBeVisible();
  await expect(page.getByRole("button", { name: "No changes" })).toBeVisible();
  await page.waitForTimeout(1500);
  await page.screenshot({ path: testInfo.outputPath("site-text.png"), fullPage: true });
  await expectNoA11yViolations(page);

  await page.getByRole("button", { name: "Use default" }).first().click();
  await page.getByRole("button", { name: "Save 1 change" }).click();
  await expect(announcement).toHaveValue("Indulgence, poured by hand");

  await page.getByRole("tab", { name: "Pages" }).click();
  await expect(page.getByRole("link", { name: /Terms & conditions/ })).toBeVisible();
});
