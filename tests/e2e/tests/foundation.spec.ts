import { expect, test } from "@playwright/test";
import { DEMO_PASSWORD, expectNoA11yViolations, signIn, totp, uniqueEmail } from "../support";

test.describe("storefront shell", () => {
  test("home renders accessibly with responsive navigation", async ({ page }, testInfo) => {
    const response = await page.goto("/");
    expect(response?.headers()["content-security-policy"]).toContain("frame-ancestors 'none'");
    expect(response?.headers()["x-content-type-options"]).toBe("nosniff");

    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
    const mobileNav = page.getByRole("navigation", { name: "Mobile" });
    const primaryNav = page.getByRole("navigation", { name: "Primary" });

    if (testInfo.project.name === "desktop") {
      await expect(primaryNav).toBeVisible();
      await expect(mobileNav).toBeHidden();
    } else {
      await expect(mobileNav).toBeVisible();
      await expect(primaryNav).toBeHidden();
    }

    // The bag button is always in the header; wishlist moves to the bottom nav on phones.
    await expect(page.getByRole("banner").getByRole("button", { name: /^Bag/ })).toBeVisible();
    const headerWishlist = page.getByRole("banner").getByRole("link", { name: "Wishlist" });
    if (testInfo.project.name === "mobile") {
      await expect(headerWishlist).toBeHidden();
    } else {
      await expect(headerWishlist).toBeVisible();
    }

    await expectNoA11yViolations(page);
    // No horizontal scroll at any viewport.
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });

  test("unknown pages show the 404 state", async ({ page }) => {
    const response = await page.goto("/definitely-not-a-page");
    expect(response?.status()).toBe(404);
    await expect(page.getByRole("heading", { name: "Page not found" })).toBeVisible();
  });
});

test.describe("customer authentication", () => {
  test("register, see account, sign out and sign back in", async ({ page }) => {
    const email = uniqueEmail("shopper");

    await page.goto("/register");
    await expectNoA11yViolations(page);
    await page.getByLabel("Full name").fill("Asha Shopper");
    await page.getByLabel("Email", { exact: true }).fill(email);
    await page.getByLabel("Password", { exact: true }).fill("correct-horse-battery");
    await page.getByLabel("Confirm password").fill("correct-horse-battery");
    await page.getByRole("button", { name: "Create account" }).click();

    await expect(page).toHaveURL(/\/account$/);
    await expect(page.getByRole("heading", { name: "Welcome, Asha Shopper" })).toBeVisible();
    await expect(page.getByText("Please verify your email address")).toBeVisible();
    await expectNoA11yViolations(page);

    await page.getByRole("button", { name: "Sign out" }).click();
    await expect(page).toHaveURL(/\/$/);

    await signIn(page, email, "wrong-password-1");
    await expect(page.getByText("These credentials do not match our records.")).toBeVisible();

    await signIn(page, email, "correct-horse-battery");
    await expect(page.getByRole("heading", { name: "Welcome, Asha Shopper" })).toBeVisible();
  });

  test("guests are sent to login from the account page", async ({ page }) => {
    await page.goto("/account");
    await expect(page).toHaveURL(/\/login\?next=(%2F|\/)account$/);
  });
});

test.describe("admin access", () => {
  test("customers are refused admin access", async ({ page }) => {
    const email = uniqueEmail("customer");
    await page.goto("/register");
    await page.getByLabel("Full name").fill("Plain Customer");
    await page.getByLabel("Email", { exact: true }).fill(email);
    await page.getByLabel("Password", { exact: true }).fill("correct-horse-battery");
    await page.getByLabel("Confirm password").fill("correct-horse-battery");
    await page.getByRole("button", { name: "Create account" }).click();
    await expect(page).toHaveURL(/\/account$/);

    await page.goto("/admin");
    await expect(page.getByRole("heading", { name: "No admin access" })).toBeVisible();
  });

  test("staff see only their areas, and can still enable 2FA from their account", async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== "desktop", "Stateful staff journey runs once (2FA can only be enabled once per seed).");

    // Local development has ADMIN_REQUIRE_2FA=false (production always requires it).
    await signIn(page, "content-manager@example.test", DEMO_PASSWORD, "/admin/content");
    await expect(page.getByRole("heading", { name: "Content", level: 1 })).toBeVisible();
    const nav = page.getByRole("navigation", { name: "Admin" });
    await expect(nav.getByText("Content")).toBeVisible();
    await expect(nav.getByText("Orders")).toHaveCount(0);

    await page.goto("/account#security");
    await page.getByRole("button", { name: "Set up two-factor" }).click();
    await page.getByLabel("Confirm your password to continue").fill(DEMO_PASSWORD);
    await page.getByRole("button", { name: "Continue" }).click();
    const keyText = await page.getByText(/Can't scan\? Enter this key:/).textContent();
    await page.getByLabel("Authentication code").fill(totp(keyText!.split(":").pop()!.trim()));
    await page.getByRole("button", { name: "Confirm and enable" }).click();
    await expect(page.getByRole("heading", { name: "Save your recovery codes" })).toBeVisible();
    await expectNoA11yViolations(page);
  });

});
