import { expect, test } from "@playwright/test";
import { expectNoA11yViolations } from "../support";

test("made-to-order explanation lives in terms and FAQ, never on the homepage", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "tablet", "Covered by the phone and desktop layouts.");

  await page.goto("/");
  await expect(page.getByRole("heading", { level: 2, name: "Good enough to eat. Made to light." })).toBeVisible();
  const home = (await page.locator("body").innerText()).toLowerCase();
  expect(home).not.toMatch(/refund|returns?\b|exchange/);

  await page.goto("/pages/terms");
  await expect(page.getByRole("heading", { level: 1, name: "Terms & conditions" })).toBeVisible();
  await expect(page.getByRole("heading", { level: 2, name: "Why every order is final" })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath("terms.png"), fullPage: true });
  await expectNoA11yViolations(page);

  await page.goto("/faq");
  await expect(page.getByRole("heading", { level: 2, name: "Can I return or exchange my order?" })).toBeVisible();
  await expect(page.getByText("made specially for you once your order is confirmed")).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath("faq.png"), fullPage: true });
});
