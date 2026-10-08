import { api, type Pagination } from "@/services/api-client";
import type { Money } from "@/types/api";

/** Typed wrappers for /api/v1/admin (API.md §3.10). */

export type Paged<T> = { items: T[]; pagination: Pagination };

export type AdminProduct = {
  uuid: string; name: string; slug: string; product_type: "physical" | "digital"; status: "draft" | "active" | "archived";
  brand: { uuid: string; name: string } | null; category: { uuid: string; name: string } | null;
  price: Money; max_price: Money; image: string | null; variant_count: number;
  stock_on_hand: number | null; stock_available: number | null; units_sold: number; updated_at: string | null;
};

export type AdminVariant = {
  uuid?: string; sku: string; name: string | null; options: Record<string, string>; price: number; compare_at_price: number | null;
  cost_price: number | null; is_active: boolean; on_hand?: number | null; reserved?: number | null; opening_stock?: number | null;
};

export type AdminProductDetail = AdminProduct & {
  short_description: string | null; description: string | null; tax_class: { uuid: string; name: string } | null;
  options: { name: string; values: string[] }[]; specifications: { label: string; value: string }[];
  is_featured: boolean; is_new: boolean; is_best_seller: boolean; seo_title: string | null; seo_description: string | null;
  published_at: string | null; media: { uuid: string; url: string; alt_text: string | null }[]; variants: AdminVariant[];
  digital: { download_limit: number | null; access_days: number | null; format: string | null } | null;
  files: { uuid: string; name: string; size_bytes: number; mime_type: string; created_at: string | null }[];
};

export type Lookups = {
  categories: { uuid: string; name: string }[];
  brands: { uuid: string; name: string }[];
  tax_classes: { uuid: string; name: string; rate_bps: number; is_default: boolean }[];
};

export const adminApi = {
  dashboard: (range: number) => api.get<Dashboard>(`/admin/dashboard?range=${range}`),
  lookups: () => api.get<Lookups>("/admin/lookups"),
  products: (query: string) => api.getPaged<AdminProduct>(`/admin/products${query}`),
  product: (uuid: string) => api.get<AdminProductDetail>(`/admin/products/${uuid}`),
  saveProduct: (uuid: string | null, body: unknown) => (uuid ? api.put<AdminProductDetail>(`/admin/products/${uuid}`, body) : api.post<AdminProductDetail>("/admin/products", body)),
  archiveProduct: (uuid: string) => api.delete(`/admin/products/${uuid}`),
  uploadMedia: (uuid: string, form: FormData) => api.post<AdminProductDetail>(`/admin/products/${uuid}/media`, form),
  deleteMedia: (uuid: string, media: string) => api.delete<AdminProductDetail>(`/admin/products/${uuid}/media/${media}`),
  uploadFile: (uuid: string, form: FormData) => api.post<AdminProductDetail>(`/admin/products/${uuid}/files`, form),
  deleteFile: (uuid: string, file: string) => api.delete<AdminProductDetail>(`/admin/products/${uuid}/files/${file}`),
};

type Kpi<T> = { current: T; previous: T };

export type Dashboard = {
  range: { days: number; from: string; to: string; bucket: "day" | "month" };
  kpis: {
    revenue: Kpi<Money>; orders: Kpi<number>; average_order_value: Kpi<Money>; new_customers: Kpi<number>;
    conversion: Kpi<number>; units_sold: Kpi<number>; refunds: Kpi<Money>; downloads: Kpi<number>;
  };
  today: { revenue: Money; orders: number };
  totals: { customers: number; staff: number; active_products: number; digital_products: number; orders_to_fulfil: number; low_stock_variants: number; needs_attention: number };
  series: { bucket: string; revenue: number; orders: number; new_customers: number }[];
  top_products: { name: string; units: number; revenue: Money; previous_units: number }[];
  by_category: { category: string; revenue: Money; units: number }[];
  by_type: { type: "physical" | "digital"; revenue: Money; units: number }[];
  orders_by_status: { status: string; count: number }[];
  low_stock: { name: string; product_uuid: string; variant: string | null; sku: string; available: number }[];
  recent_orders: { order_number: string; email: string; status: string; payment_status: string; total: Money; placed_at: string | null }[];
};

export type AdminCategory = { uuid: string; name: string; slug: string; description: string | null; sort_order?: number; is_active: boolean; products_count: number; image?: string | null };
export type InventoryRow = { variant_uuid: string; sku: string; variant_name: string | null; product_name: string; product_uuid: string; on_hand: number; reserved: number; available: number; low: boolean };
export type LedgerRow = { type: string; quantity: number; balance_after: number; note: string | null; actor: string | null; created_at: string };

export const taxonomyApi = {
  categories: () => api.get<AdminCategory[]>("/admin/categories"),
  saveCategory: (uuid: string | null, body: unknown) => (uuid ? api.put(`/admin/categories/${uuid}`, body) : api.post("/admin/categories", body)),
  deleteCategory: (uuid: string) => api.delete(`/admin/categories/${uuid}`),
  brands: () => api.get<AdminCategory[]>("/admin/brands"),
  saveBrand: (uuid: string | null, body: unknown) => (uuid ? api.put(`/admin/brands/${uuid}`, body) : api.post("/admin/brands", body)),
  deleteBrand: (uuid: string) => api.delete(`/admin/brands/${uuid}`),
};

export const inventoryApi = {
  list: (query: string) => api.getPaged<InventoryRow>(`/admin/inventory${query}`),
  adjust: (variant: string, body: { type: string; quantity: number; note?: string }) => api.post<{ on_hand: number; available: number }>(`/admin/inventory/${variant}/adjust`, body),
  ledger: (variant: string) => api.get<LedgerRow[]>(`/admin/inventory/${variant}/transactions`),
};

export type AdminOrderDetail = import("@/features/orders/types").OrderDetail & {
  customer: { uuid: string; name: string; email: string } | null;
  requires_attention: boolean;
  payments: { uuid: string; provider: string; status: string; amount: Money; method: string | null; provider_payment_id: string | null; created_at: string }[];
  refunds: { uuid: string; amount: Money; reason: string; status: string; created_at: string }[];
  allowed_transitions: string[];
  ip_address: string | null;
  courier_enabled: boolean;
  courier_shipments: CourierShipment[];
};

export type CourierShipment = {
  uuid: string; provider: string | null; carrier: string | null; tracking_number: string | null; tracking_url: string | null;
  status: "booking" | "pickup_scheduled" | "failed" | "in_transit" | "delivered" | "exception" | null;
  courier_status: string | null; pickup_scheduled_at: string | null; last_error: string | null; last_event_at: string | null;
  shipped_at: string | null; delivered_at: string | null;
};

export const ordersAdminApi = {
  list: (query: string) => api.getPaged<import("@/features/orders/types").OrderSummary>(`/admin/orders${query}`),
  get: (n: string) => api.get<AdminOrderDetail>(`/admin/orders/${n}`),
  status: (n: string, to: string, reason?: string) => api.post<AdminOrderDetail>(`/admin/orders/${n}/status`, { to, reason }),
  ship: (n: string, body: { carrier: string; tracking_number: string; tracking_url?: string }) => api.post<AdminOrderDetail>(`/admin/orders/${n}/shipments`, body),
  refund: (n: string, body: { amount: number; reason: string; restock: boolean }) =>
    api.post<AdminOrderDetail>(`/admin/orders/${n}/refunds`, body, { headers: { "Idempotency-Key": crypto.randomUUID() } }),
  cancel: (n: string, reason: string) => api.post<AdminOrderDetail>(`/admin/orders/${n}/cancel`, { reason }),
  bookCourier: (n: string) => api.post<AdminOrderDetail>(`/admin/orders/${n}/courier-booking`),
};

export type AdminCustomer = { uuid: string; name: string; email: string; is_active: boolean; orders_count: number; total_spent: Money; created_at: string | null; last_login_at: string | null };
export type AdminCustomerDetail = {
  uuid: string; name: string; email: string; phone: string | null; is_active: boolean; email_verified: boolean; created_at: string | null; last_login_at: string | null;
  stats: { total_orders: number; total_spent: Money; average_order_value: Money; last_purchase_at: string | null; wishlist_items: number; downloads: number; refunded_orders: number };
  orders: import("@/features/orders/types").OrderSummary[];
};
export type AdminEntitlement = { uuid: string; email: string; product: string | null; order_number: string; status: string; downloads_used: number; download_limit: number | null; expires_at: string | null; created_at: string };
export type AdminCoupon = { uuid: string; code: string; description: string | null; type: "percentage" | "fixed" | "free_shipping"; value: number; max_discount: number | null; min_order_total: number | null; first_order_only: boolean; usage_limit: number | null; usage_count: number; per_customer_limit: number | null; starts_at: string | null; ends_at: string | null; is_active: boolean };
export type AdminPageItem = { uuid: string; slug: string; title: string; status: "draft" | "published"; seo_description: string | null; updated_at: string; body?: string | null };
export type SalesReport = {
  range: { from: string; to: string };
  summary: { orders: number; gross_sales: Money; discounts: Money; shipping: Money; tax: Money; refunds: Money; net_sales: Money; average_order_value: Money };
  daily: { date: string; orders: number; revenue: number }[];
  by_product: { name: string; type: string; units: number; revenue: Money }[];
  by_category: { category: string; units: number; revenue: Money }[];
};
export type StoreSettings = {
  store: { name: string; currency: string; state_code: string; support_email: string | null; legal_name: string | null; gstin: string | null; address: string | null };
  tax_classes: { uuid: string; name: string; rate_bps: number; is_default: boolean }[];
  shipping_methods: { uuid: string; code: string; zone: string; name: string; amount: number; free_over: number | null; days_min: number; days_max: number; is_active: boolean }[];
  payments: { key: string; name: string; description: string }[];
  security: { admin_requires_2fa: boolean; locked: boolean; you_have_2fa: boolean };
  integrations: {
    razorpay: { configured: boolean; webhook_configured: boolean; test_mode: boolean; webhook_url: string };
    test_gateway: boolean;
    shiprocket: { configured: boolean; webhook_configured: boolean; pickup_location: string; webhook_url: string };
  };
};
export type AuditEntry = { uuid: string; action: string; actor: string; subject: string | null; before: Record<string, unknown> | null; after: Record<string, unknown> | null; ip_address: string | null; created_at: string };

export const miscAdminApi = {
  customers: (query: string) => api.getPaged<AdminCustomer>(`/admin/customers${query}`),
  customer: (uuid: string) => api.get<AdminCustomerDetail>(`/admin/customers/${uuid}`),
  setCustomerActive: (uuid: string, is_active: boolean) => api.patch(`/admin/customers/${uuid}`, { is_active }),
  entitlements: (query: string) => api.getPaged<AdminEntitlement>(`/admin/digital/entitlements${query}`),
  updateEntitlement: (uuid: string, action: "revoke" | "restore" | "reset") => api.patch(`/admin/digital/entitlements/${uuid}`, { action }),
  downloads: () => api.get<{ email: string; file: string; status: string; deny_reason: string | null; ip_address: string | null; created_at: string }[]>("/admin/digital/downloads"),
  coupons: () => api.get<AdminCoupon[]>("/admin/coupons"),
  saveCoupon: (uuid: string | null, body: unknown) => (uuid ? api.put<AdminCoupon>(`/admin/coupons/${uuid}`, body) : api.post<AdminCoupon>("/admin/coupons", body)),
  deleteCoupon: (uuid: string) => api.delete(`/admin/coupons/${uuid}`),
  sales: (from: string, to: string) => api.get<SalesReport>(`/admin/reports/sales?from=${from}&to=${to}`),
  pages: () => api.get<AdminPageItem[]>("/admin/pages"),
  page: (uuid: string) => api.get<AdminPageItem>(`/admin/pages/${uuid}`),
  savePage: (uuid: string | null, body: unknown) => (uuid ? api.put<AdminPageItem>(`/admin/pages/${uuid}`, body) : api.post<AdminPageItem>("/admin/pages", body)),
  deletePage: (uuid: string) => api.delete(`/admin/pages/${uuid}`),
  settings: () => api.get<StoreSettings>("/admin/settings"),
  saveSettings: (body: unknown) => api.put("/admin/settings", body),
  testShiprocket: () => api.post<{ pickup_locations: string[]; pickup_location_found: boolean }>("/admin/settings/shiprocket/test"),
  saveSecurity: (body: { admin_require_2fa: boolean; password: string }) => api.put<{ admin_requires_2fa: boolean }>("/admin/settings/security", body),
  audit: (page: number) => api.getPaged<AuditEntry>(`/admin/audit-logs?page=${page}`),
};
