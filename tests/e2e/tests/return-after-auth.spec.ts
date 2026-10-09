import { expect, test } from "@playwright/test";
import { uniqueEmail } from "../support";

test("a shopper who signs up from a product page lands back on that product", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "tablet", "Covered by the phone and desktop layouts.");

  await page.goto("/shop");
  const product = page.locator("a[href^='/product/']").first();
  const productPath = (await product.getAttribute("href"))!;
  await page.goto(productPath);
  await expect(page.getByRole("heading", { level: 1 })).toBeVisible();

  await page.getByRole("link", { name: "Sign in" }).first().click();
  await expect(page).toHaveURL(new RegExp(`/login\\?next=${encodeURIComponent(productPath).replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`));
  await page.getByRole("link", { name: /create an account/i }).click();
  await expect(page).toHaveURL(/\/register\?next=/);

  await page.getByLabel("Full name", { exact: false }).first().fill("Meera");
  await page.getByLabel("Email", { exact: true }).fill(uniqueEmail("meera"));
  await page.getByLabel("Password", { exact: true }).fill("abc123");
  await page.getByLabel(/confirm/i).fill("abc123");
  await page.getByRole("button", { name: /create account/i }).click();

  await expect(page).toHaveURL(new RegExp(`${productPath.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}$`));
});

test("checkout offers sign-in and comes straight back to checkout", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Checkout journey runs on desktop.");

  await page.goto("/shop");
  await page.locator("a[href^='/product/']").first().click();
  await page.getByRole("button", { name: /add to (bag|cart)/i }).first().click();
  await page.goto("/checkout");
  await page.getByRole("link", { name: "Sign in", exact: true }).last().click();
  await expect(page).toHaveURL(/\/login\?next=%2Fcheckout/);
  await page.getByRole("link", { name: /create an account/i }).click();
  await page.getByLabel("Full name", { exact: false }).first().fill("Arun");
  await page.getByLabel("Email", { exact: true }).fill(uniqueEmail("arun"));
  await page.getByLabel("Password", { exact: true }).fill("candle");
  await page.getByLabel(/confirm/i).fill("candle");
  await page.getByRole("button", { name: /create account/i }).click();
  await expect(page).toHaveURL(/\/checkout$/);
  // The bag came along and the new account's email is already filled in.
  await expect(page.getByRole("heading", { name: "Order summary" })).toBeVisible();
  await expect(page.locator("#email:visible")).toHaveValue(/^arun-/);
});
