import type { Money } from "@/types/api";

export type Entitlement = {
  uuid: string;
  order_number: string;
  product: { name: string; slug: string; image: string | null } | null;
  status: string;
  downloads_used: number;
  download_limit: number | null;
  expires_at: string | null;
  usable: boolean;
  files: { uuid: string; name: string; size_bytes: number; mime_type: string }[];
  purchased_at: string;
};

export type OrderSummary = {
  uuid: string;
  order_number: string;
  status: string;
  status_label: string;
  payment_status: string;
  fulfillment_status: string;
  email: string;
  requires_shipping: boolean | null;
  placed_at: string | null;
  paid_at: string | null;
  currency: string;
  totals: { subtotal: Money; discount: Money; shipping: Money; tax: Money; total: Money; refunded: Money };
  item_count: number | null;
};

export type OrderDetail = OrderSummary & {
  phone: string | null;
  tax_breakdown: { code: string; amount: Money }[];
  coupon_code: string | null;
  shipping_method: { name: string; days_min: number; days_max: number } | null;
  shipping_address: { name: string; line1: string; line2: string | null; city: string; state_code: string; postal_code: string; country_code: string } | null;
  items: { uuid: string; name: string; variant_name: string | null; sku: string; product_type: string; product_slug: string | null; image: string | null; unit_price: Money; quantity: number; line_total: Money }[];
  shipments: { carrier: string | null; tracking_number: string | null; tracking_url: string | null; shipped_at: string | null }[];
  history: { from: string | null; to: string; actor: string; reason: string | null; at: string }[];
  downloads: Entitlement[];
  can_pay: boolean;
  can_cancel: boolean;
};
