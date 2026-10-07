import { describe, expect, it } from "vitest";
import { visibleAdminNav } from "@/features/admin/navigation";
import { t } from "./i18n";
import { formatMoney } from "./money";
import { safeRedirectPath } from "./safe-redirect";

describe("formatMoney", () => {
  it("formats minor units using the currency exponent", () => {
    expect(formatMoney({ amount: 249900, currency: "INR" }, "en-IN")).toBe("₹2,499.00");
    expect(formatMoney({ amount: 1999, currency: "USD" }, "en-US")).toBe("$19.99");
    expect(formatMoney({ amount: 500, currency: "JPY" }, "en-US")).toBe("¥500");
  });
});

describe("safeRedirectPath", () => {
  it.each([
    ["/orders", "/orders"],
    ["https://evil.example", "/account"],
    ["//evil.example", "/account"],
    ["/\\evil.example", "/account"],
    [null, "/account"],
  ])("%s -> %s", (input, expected) => {
    expect(safeRedirectPath(input)).toBe(expected);
  });
});

describe("t", () => {
  it("interpolates variables", () => {
    expect(t("account.welcome", { name: "Asha" })).toBe("Welcome, Asha");
  });
});

describe("visibleAdminNav", () => {
  it("filters items by permission", () => {
    const labels = visibleAdminNav(["content.manage"]).map((i) => i.label);
    expect(labels).toEqual(["admin.content"]);
  });
});
