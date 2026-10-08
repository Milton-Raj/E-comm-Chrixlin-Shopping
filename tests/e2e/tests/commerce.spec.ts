import { expect, test, type Page } from "@playwright/test";
import { DEMO_PASSWORD, expectNoA11yViolations, signIn, uniqueEmail } from "../support";

async function fillAddress(page: Page) {
  await page.getByLabel("Full name").fill("Asha Shopper");
  await page.getByLabel("Address", { exact: true }).fill("12 Marina Road");
  await page.getByLabel("City").fill("Chennai");
  await page.getByLabel("PIN code").fill("600001");
  await page.getByRole("button", { name: "Continue" }).click();
}

test("guest buys a physical product with a coupon and pays (test gateway)", async ({ page }) => {
  await page.goto("/product/grained-leather-card-case");
  await page.getByRole("button", { name: "Add to bag" }).click();
  await page.getByRole("dialog", { name: "Your bag" }).getByRole("link", { name: "View bag" }).click();

  await expect(page.getByRole("heading", { name: "Your bag", level: 1 })).toBeVisible();
  await page.getByLabel("Promo code").fill("FREESHIP");
  await page.getByRole("button", { name: "Apply" }).click();
  await expect(page.getByText("FREESHIP").first()).toBeVisible();
  await page.getByRole("link", { name: "Proceed to checkout" }).click();

  const guestEmail = uniqueEmail("guest");
  await page.getByLabel("Email").fill(guestEmail);
  await page.getByRole("button", { name: "Continue" }).click();
  await fillAddress(page);
  await page.getByRole("button", { name: "Continue to payment" }).click();
  await expectNoA11yViolations(page);

  await page.getByRole("button", { name: /^Pay ₹/ }).click();
  await expect(page.getByText("Test payment mode")).toBeVisible();
  await page.getByRole("button", { name: "Simulate failure" }).click();
  await expect(page.getByText("The test payment was declined")).toBeVisible();
  await page.getByRole("button", { name: /^Pay ₹/ }).click();

  await expect(page).toHaveURL(/\/checkout\/confirmation\/ORD-\d{4}-\d{6}\?token=/);
  await expect(page.getByRole("heading", { name: "Thank you for your order" })).toBeVisible();
  await expect(page.getByText("Payment confirmed", { exact: true })).toBeVisible();
  await expect(page.getByRole("link", { name: "Grained Leather Card Case" })).toBeVisible();
  await expectNoA11yViolations(page);

  // The GST tax invoice is downloadable (guest token) and was emailed as a PDF attachment.
  await expect(page.getByText(/tax invoice INV\d{2}-\d{2}\/\d{6} attached/)).toBeVisible();
  const download = page.waitForEvent("download");
  await page.getByRole("button", { name: "Download invoice" }).click();
  expect((await download).suggestedFilename()).toMatch(/^Invoice-INV\d{2}-\d{2}-\d{6}\.pdf$/);

  const mailpit = process.env.E2E_MAILPIT_URL ?? "http://localhost:8025";
  const search = await (await page.request.get(`${mailpit}/api/v1/search?query=${encodeURIComponent(`to:${guestEmail}`)}`)).json();
  expect(search.messages_count ?? search.messages.length).toBeGreaterThan(0);
  const message = await (await page.request.get(`${mailpit}/api/v1/message/${search.messages[0].ID}`)).json();
  expect(message.Subject).toMatch(/^Order ORD-\d{4}-\d{6} confirmed$/);
  expect(message.Attachments.map((a: { FileName: string; ContentType: string }) => [a.FileName, a.ContentType]))
    .toEqual([[expect.stringMatching(/^Invoice-INV\d{2}-\d{2}-\d{6}\.pdf$/), "application/pdf"]]);
});

test("customer buys a digital product and downloads it", async ({ page, browserName }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "One download journey is enough; it changes download counts.");

  const email = uniqueEmail("reader");
  await page.goto("/register");
  await page.getByLabel("Full name").fill("Rhea Reader");
  await page.getByLabel("Email", { exact: true }).fill(email);
  await page.getByLabel("Password", { exact: true }).fill("correct-horse-battery");
  await page.getByLabel("Confirm password").fill("correct-horse-battery");
  await page.getByRole("button", { name: "Create account" }).click();
  await expect(page).toHaveURL(/\/account$/);

  await page.goto("/digital/the-art-of-slow-living");
  await page.getByRole("button", { name: "Buy now" }).click();
  await expect(page).toHaveURL(/\/checkout$/);
  await expect(page.getByLabel("Email")).toHaveValue(email);
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByRole("button", { name: /^Pay ₹/ }).click();
  await page.getByRole("button", { name: /^Pay ₹/ }).click();

  await expect(page.getByRole("heading", { name: "Your downloads" })).toBeVisible();
  const downloadPromise = page.waitForEvent("download");
  await page.getByRole("button", { name: /sample\.pdf/ }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toMatch(/\.pdf$/);
  expect(browserName).toBe("chromium");

  await page.goto("/downloads");
  await expect(page.getByText("1 of 5 downloads used")).toBeVisible();
});

test("admin manages the catalogue and fulfils an order", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Admin journeys run on desktop.");

  await signIn(page, "admin@example.test", DEMO_PASSWORD, "/admin");
  await expect(page.getByRole("heading", { name: "Dashboard", level: 1 })).toBeVisible();
  await expect(page.getByText("Revenue per day")).toBeVisible();
  await expectNoA11yViolations(page);

  // Create a product and see it on the storefront.
  const name = `Atelier Test Scarf ${Date.now()}`;
  await page.goto("/admin/products/new");
  await page.getByLabel(/^Name\b/).fill(name);
  await page.getByLabel("Short description").fill("A test scarf created by the E2E suite.");
  await page.getByLabel("SKU").fill(`E2E-${Date.now()}`);
  await page.getByLabel("Price", { exact: true }).fill("1999");
  await page.getByLabel("Opening stock").fill("7");
  await page.getByLabel("Status").selectOption("active");
  await page.getByRole("button", { name: "Save product" }).click();
  await expect(page.getByText("Product created.")).toBeVisible();
  await expect(page).toHaveURL(/\/admin\/products\/[0-9a-f-]{36}$/);

  await page.goto(`/search?q=${encodeURIComponent(name)}`);
  await expect(page.getByRole("link", { name })).toBeVisible();

  // Ship a processing order.
  await page.goto("/admin/orders?status=processing");
  await page.getByRole("link", { name: /^ORD-/ }).first().click();
  const stages = page.getByRole("list", { name: "Delivery stages" });
  await expect(stages.locator('[aria-current="step"]')).toContainText("Processing");
  await page.getByRole("button", { name: "Mark packed" }).click();
  await expect(page.getByText(/marked packed/)).toBeVisible();
  await expect(stages.locator('[aria-current="step"]')).toContainText("Packed");
  await page.getByLabel("Tracking number").fill("BD123456789");
  await page.getByRole("button", { name: "Mark shipped" }).click();
  await expect(page.getByText("Marked as shipped")).toBeVisible();
  await page.getByRole("button", { name: "Mark delivered" }).click();
  await expect(page.getByText(/marked delivered/)).toBeVisible();
  await expect(stages.locator('[aria-current="step"]')).toContainText("Delivered");
});

test("wishlist hearts turn red and the header shows the saved count", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name === "mobile", "The header heart is hidden on phones; the bottom nav carries the same badge.");

  await page.goto("/register");
  await page.getByLabel("Full name").fill("Wren Wishlist");
  await page.getByLabel("Email", { exact: true }).fill(uniqueEmail("wish"));
  await page.getByLabel("Password", { exact: true }).fill("correct-horse-battery");
  await page.getByLabel("Confirm password").fill("correct-horse-battery");
  await page.getByRole("button", { name: "Create account" }).click();
  await expect(page).toHaveURL(/\/account$/);

  await page.goto("/shop");
  const heart = page.getByRole("button", { name: /^Add .+ to wishlist$/ }).first();
  const label = (await heart.getAttribute("aria-label")) ?? "";
  const product = label.replace(/^Add /, "").replace(/ to wishlist$/, "");
  await heart.click();
  const saved = page.getByRole("button", { name: `Remove ${product} from wishlist` });
  await expect(saved).toHaveAttribute("aria-pressed", "true");
  await expect(page.getByRole("banner").getByRole("link", { name: "Wishlist, 1 saved" })).toBeVisible();

  await saved.click();
  await expect(page.getByRole("button", { name: `Add ${product} to wishlist` })).toHaveAttribute("aria-pressed", "false");
  await expect(page.getByRole("banner").getByRole("link", { name: "Wishlist", exact: true })).toBeVisible();
});

test("admin dashboard switches ranges and exports Excel workbooks", async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== "desktop", "Admin reporting is checked once, on desktop.");
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));

  await signIn(page, "admin@example.test", DEMO_PASSWORD, "/admin");
  for (const range of ["7 days", "90 days", "12 months", "7 days", "30 days"]) {
    await page.getByRole("button", { name: range }).click();
    await expect(page.getByRole("button", { name: range })).toHaveAttribute("aria-pressed", "true");
    await expect(page.getByText(/Revenue per (day|month)/).first()).toBeVisible();
  }
  expect(errors).toEqual([]);

  await page.goto("/admin/orders");
  const orders = page.waitForEvent("download");
  await page.getByRole("button", { name: "Export to Excel" }).click();
  expect((await orders).suggestedFilename()).toMatch(/-orders-\d{4}-\d{2}-\d{2}\.xlsx$/);

  await page.goto("/admin/reports");
  const report = page.waitForEvent("download");
  await page.getByRole("button", { name: "Download full report (Excel)" }).click();
  expect((await report).suggestedFilename()).toMatch(/-report-\d{4}-\d{2}-\d{2}_to_\d{4}-\d{2}-\d{2}\.xlsx$/);
});

