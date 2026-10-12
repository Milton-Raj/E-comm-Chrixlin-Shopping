import type { MessageKey } from "@/lib/i18n";

/**
 * Admin sidebar (PRD §42). `permission` only hides links — the API enforces every permission.
 */
export type AdminNavItem = { href: string; label: MessageKey | string; permission: string | null; available: boolean; group: string | null };

/** Daily work first (no heading), then the less-used areas in labelled groups. */
export const adminNav: AdminNavItem[] = [
  { href: "/admin", label: "admin.dashboard", permission: "dashboard.view", available: true, group: null },
  { href: "/admin/orders", label: "admin.orders", permission: "orders.view", available: true, group: null },
  { href: "/admin/products", label: "Products", permission: "products.view", available: true, group: null },
  { href: "/admin/inventory", label: "admin.inventory", permission: "inventory.view", available: true, group: null },
  { href: "/admin/customers", label: "admin.customers", permission: "customers.view", available: true, group: null },
  { href: "/admin/reports", label: "admin.reports", permission: "reports.view", available: true, group: null },
  { href: "/admin/coupons", label: "Coupons", permission: "coupons.manage", available: true, group: "Grow sales" },
  { href: "/admin/marketing", label: "Marketing", permission: "promotions.manage", available: true, group: "Grow sales" },
  { href: "/admin/reviews", label: "Reviews", permission: "reviews.moderate", available: true, group: "Grow sales" },
  { href: "/admin/enquiries", label: "Enquiries", permission: "support.view", available: true, group: "Grow sales" },
  { href: "/admin/categories", label: "Categories & brands", permission: "categories.manage", available: true, group: "Catalogue & site" },
  { href: "/admin/digital", label: "admin.digital", permission: "downloads.view", available: true, group: "Catalogue & site" },
  { href: "/admin/content", label: "admin.content", permission: "content.manage", available: true, group: "Catalogue & site" },
  { href: "/admin/settings", label: "admin.settings", permission: "settings.manage", available: true, group: "Store setup" },
  { href: "/admin/staff", label: "Staff", permission: "users.manage", available: true, group: "Store setup" },
  { href: "/admin/audit", label: "Audit log", permission: "audit.view", available: true, group: "Store setup" },
  { href: "/admin/account", label: "My account", permission: null, available: true, group: "Store setup" },
];

export function visibleAdminNav(permissions: readonly string[]): AdminNavItem[] {
  return adminNav.filter((item) => item.permission === null || permissions.includes(item.permission));
}

/** Consecutive items sharing a group, in menu order. */
export function groupAdminNav(items: readonly AdminNavItem[]): { group: string | null; items: AdminNavItem[] }[] {
  const groups: { group: string | null; items: AdminNavItem[] }[] = [];
  for (const item of items) {
    const last = groups.at(-1);
    if (last && last.group === item.group) last.items.push(item);
    else groups.push({ group: item.group, items: [item] });
  }
  return groups;
}
