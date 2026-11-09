import { api } from "@/services/api-client";
import type { CurrencyRate } from "@/lib/money";
import type { Cart } from "@/features/cart/types";

/** GET /localization (API.md §3.2): where the visitor is and what they can pay in. */
export type Localization = {
  enabled: boolean;
  store_currency: string;
  store_country: string;
  detected_country: string | null;
  default_currency: string;
  /** The store currency first (rate "1"), then each offered currency with today's rate. */
  currencies: CurrencyRate[];
  /** ISO country codes that can be delivered to. */
  countries: string[];
  delivers_to_visitor: boolean;
  unavailable_message: string;
  rates_updated_at: string | null;
};

export const localizationKey = ["localization"] as const;

export const localizationApi = {
  get: () => api.get<Localization>("/localization"),
  setCartCurrency: (currency: string) => api.put<Cart>("/cart/currency", { currency }),
};
