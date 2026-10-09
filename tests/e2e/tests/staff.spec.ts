import { expect, test } from "@playwright/test";
import { DEMO_PASSWORD, expectNoA11yViolations, signIn, uniqueEmail } from "../support";

test("owner invites a staff member and manages their access", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "tablet", "Covered by the phone and desktop layouts.");

  await signIn(page, "admin@example.test", DEMO_PASSWORD, "/admin/staff");
  await expect(page.getByRole("heading", { name: "Staff", level: 1 })).toBeVisible();

  const email = uniqueEmail("staff");
  await page.getByRole("button", { name: "Add staff member" }).click();
  await page.getByLabel(/^Full name/).fill("Test Packer");
  await page.getByRole("textbox", { name: "Email (required)" }).fill(email);
  await page.getByLabel(/^Role/).selectOption("order-manager");
  await expect(page.getByText("Order Manager can:")).toBeVisible();
  await page.getByLabel(/^Your password/).fill(DEMO_PASSWORD);
  await page.screenshot({ path: testInfo.outputPath("staff-add.png"), fullPage: true });
  await page.getByRole("button", { name: "Send invitation" }).click();
  await expect(page.getByText(`Invitation sent to ${email}.`)).toBeVisible();

  const row = page.getByRole("listitem").filter({ hasText: email });
  await expect(row.getByText("Not signed in yet")).toBeVisible();
  await row.getByRole("button", { name: "Manage" }).click();
  await row.getByLabel(/^Your password/).fill(DEMO_PASSWORD);
  await row.getByRole("button", { name: "Deactivate" }).click();
  await expect(row.getByText("deactivated")).toBeVisible();

  await page.screenshot({ path: testInfo.outputPath("staff-list.png"), fullPage: true });
  await expectNoA11yViolations(page);
});
