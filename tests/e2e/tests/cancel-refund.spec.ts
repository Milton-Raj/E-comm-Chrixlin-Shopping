import { expect, test } from "@playwright/test";
import { DEMO_PASSWORD, signIn } from "../support";

test("admin cancels a paid order with an automatic refund", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Admin journeys run on desktop.");

  await signIn(page, "admin@example.test", DEMO_PASSWORD, "/admin/orders?status=processing");
  await expect(page.getByRole("heading", { name: "Orders", level: 1 })).toBeVisible();
  await page.locator("a[href^='/admin/orders/']").first().click();

  await expect(page.getByRole("heading", { name: "Cancel & refund" })).toBeVisible();
  await page.getByLabel(/^Reason/).fill("Customer cancelled within 6 hours");
  await page.getByRole("button", { name: "Cancel & refund" }).click();
  await page.getByRole("button", { name: /^Confirm: cancel & refund/ }).click();
  await expect(page.getByText("Order cancelled. The money is on its way back to the customer")).toBeVisible();
  await expect(page.getByRole("heading", { name: "Cancel & refund" })).toHaveCount(0);
});
