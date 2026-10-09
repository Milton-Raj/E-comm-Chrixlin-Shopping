import { expect, test } from "@playwright/test";

test("minus at quantity 1 takes the item out of the bag", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "tablet", "Covered by the phone and desktop layouts.");

  await page.goto("/product/grained-leather-card-case");
  await page.getByRole("button", { name: "Add to bag" }).click();
  const bag = page.getByRole("dialog", { name: "Your bag" });
  await expect(bag.getByRole("group", { name: /^Quantity for/ })).toBeVisible();

  await bag.getByRole("button", { name: "Increase quantity" }).click();
  await expect(bag.locator("output")).toHaveText("2");
  await bag.getByRole("button", { name: "Decrease quantity" }).click();
  await expect(bag.locator("output")).toHaveText("1");
  await bag.getByRole("group", { name: /^Quantity for/ }).getByRole("button", { name: /^Remove / }).click();

  await expect(bag.getByRole("group", { name: /^Quantity for/ })).toHaveCount(0);
  await expect(bag.getByText(/your bag is empty/i)).toBeVisible();
});
