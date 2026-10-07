import type { Money } from "@/types/api";

export type Address = {
  name: string;
  phone?: string | null;
  line1: string;
  line2?: string | null;
  city: string;
  state_code: string;
  postal_code: string;
  country_code: string;
};

export type ShippingOption = { code: string; name: string; description: string | null; amount: Money; days_min: number; days_max: number };

export type CartItem = {
  uuid: string;
  quantity: number;
  product: { uuid: string; slug: string; name: string; product_type: "physical" | "digital"; brand: string | null; image: { url: string; alt: string } | null };
  variant: { uuid: string; sku: string; name: string | null };
  unit_price: Money;
  line_total: Money;
  available: boolean;
  max_quantity: number;
};

export type Cart = {
  uuid: string;
  item_count: number;
  currency: string;
  requires_shipping: boolean;
  items: CartItem[];
  totals: { subtotal: Money; discount: Money; shipping: Money; tax: Money; total: Money };
  tax_breakdown: { code: string; amount: Money }[];
  tax_inclusive: boolean;
  coupon: { code: string; description: string | null } | null;
  coupon_error: string | null;
  contact: { email: string | null; phone: string | null };
  shipping_address: Address | null;
  shipping_method: ShippingOption | null;
  shipping_options: ShippingOption[];
  warnings: { code: string; item: string; message: string }[];
};

export type Gateway = { key: string; name: string; description: string };

export type PlacedOrder = {
  order_number: string;
  access_token: string | null;
  payment: { gateway: string; client_payload: Record<string, unknown> };
};
