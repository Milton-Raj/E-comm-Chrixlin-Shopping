import { expect, test, type Page } from "@playwright/test";
import { uniqueEmail } from "../support";

async function addToBagAndCheckout(page: Page, expectPhone?: string) {
  await page.goto("/product/grained-leather-card-case");
  await page.getByRole("button", { name: "Add to bag" }).click();
  await page.getByRole("dialog", { name: "Your bag" }).getByRole("link", { name: "View bag" }).click();
  await page.getByRole("link", { name: "Proceed to checkout" }).click();
  if (expectPhone) await expect(page.locator("#phone:visible")).toHaveValue(expectPhone);
  await page.getByRole("button", { name: "Continue" }).click(); // contact: email (and phone) already filled
}

test("a returning customer confirms (or changes) last time's delivery address", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Checkout journey runs on desktop.");

  await page.goto("/register");
  await page.getByLabel("Full name", { exact: false }).first().fill("Repeat Buyer");
  await page.getByLabel("Email", { exact: true }).fill(uniqueEmail("repeat"));
  await page.getByLabel("Password", { exact: true }).fill("candle1");
  await page.getByLabel(/confirm/i).fill("candle1");
  await page.getByRole("button", { name: /create account/i }).click();
  await expect(page).toHaveURL(/\/account/);

  // First order: the address is typed in full.
  await addToBagAndCheckout(page);
  await page.getByLabel("Full name").fill("Repeat Buyer");
  await page.getByLabel("Address", { exact: true }).fill("12 Marina Road");
  await page.getByLabel("City").fill("Chennai");
  await page.getByLabel("PIN code").fill("600001");
  await page.getByLabel("Phone for the courier").fill("9876543210");
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByRole("button", { name: "Continue to payment" }).click();
  await page.getByRole("button", { name: /^Pay ₹/ }).click();
  await expect(page.getByText("Test payment mode")).toBeVisible();
  await page.getByRole("button", { name: /^Pay ₹/ }).click();
  await expect(page.getByRole("heading", { name: "Thank you for your order" })).toBeVisible();

  // Next order: the saved address is shown for confirmation, not applied silently.
  await addToBagAndCheckout(page, "9876543210");
  await expect(page.getByText("Deliver to the same address as last time?")).toBeVisible();
  await expect(page.getByText("12 Marina Road")).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath("confirm-address.png"), fullPage: true });

  // They can change it: the form opens pre-filled.
  await page.getByRole("button", { name: "Change address" }).click();
  await expect(page.getByLabel("Address", { exact: true })).toHaveValue("12 Marina Road");
  await page.getByLabel("Address", { exact: true }).fill("44 Beach Road");
  await page.getByRole("button", { name: "Continue" }).click();
  await expect(page.getByRole("button", { name: "Continue to payment" })).toBeVisible();
  await expect(page.getByText(/Repeat Buyer, Chennai 600001/)).toBeVisible();
});
