import type { MessageKey } from "@/lib/i18n";

/**
 * Admin sidebar (PRD §42). `permission` only hides links — the API enforces every permission.
 */
export type AdminNavItem = { href: string; label: MessageKey | string; permission: string | null; available: boolean };

export const adminNav: AdminNavItem[] = [
  { href: "/admin", label: "admin.dashboard", permission: "dashboard.view", available: true },
  { href: "/admin/products", label: "Products", permission: "products.view", available: true },
  { href: "/admin/categories", label: "Categories & brands", permission: "categories.manage", available: true },
  { href: "/admin/inventory", label: "admin.inventory", permission: "inventory.view", available: true },
  { href: "/admin/orders", label: "admin.orders", permission: "orders.view", available: true },
  { href: "/admin/customers", label: "admin.customers", permission: "customers.view", available: true },
  { href: "/admin/digital", label: "admin.digital", permission: "downloads.view", available: true },
  { href: "/admin/coupons", label: "Coupons", permission: "coupons.manage", available: true },
  { href: "/admin/reports", label: "admin.reports", permission: "reports.view", available: true },
  { href: "/admin/content", label: "admin.content", permission: "content.manage", available: true },
  { href: "/admin/staff", label: "Staff", permission: "users.manage", available: true },
  { href: "/admin/settings", label: "admin.settings", permission: "settings.manage", available: true },
  { href: "/admin/audit", label: "Audit log", permission: "audit.view", available: true },
  { href: "/admin/account", label: "My account", permission: null, available: true },
];

export function visibleAdminNav(permissions: readonly string[]): AdminNavItem[] {
  return adminNav.filter((item) => item.permission === null || permissions.includes(item.permission));
}
