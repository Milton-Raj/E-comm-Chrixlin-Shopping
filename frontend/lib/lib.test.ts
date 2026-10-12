import { describe, expect, it } from "vitest";
import { adminNav, groupAdminNav, visibleAdminNav } from "@/features/admin/navigation";
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
    expect(labels).toEqual(["admin.content", "My account"]);
    expect(visibleAdminNav(["users.manage"]).map((i) => i.href)).toEqual(["/admin/staff", "/admin/account"]);
  });

  it("puts daily work first and groups the rest", () => {
    const sections = groupAdminNav(adminNav);
    expect(sections.map((s) => s.group)).toEqual([null, "Grow sales", "Catalogue & site", "Store setup"]);
    expect(sections[0]?.items.slice(0, 3).map((i) => i.href)).toEqual(["/admin", "/admin/orders", "/admin/products"]);
  });
});
