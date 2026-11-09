import { api } from "@/services/api-client";
import type { CurrencyRate } from "@/lib/money";
import type { Cart } from "@/features/cart/types";

/** GET /localization (API.md §3.2): where the visitor is and what they can pay in. */
export type Localization = {
  enabled: boolean;
  store_currency: string;
  store_country: string;
  detected_country: string | null;
  /** "ip" (server lookup) or "browser" (guessed from the browser's language region). */
  detected_by: "ip" | "browser" | null;
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

/** The region of the browser's preferred language ("en-US" → "US"); a fallback when the IP gives no country. */
export function browserRegion(languages: readonly string[] = typeof navigator === "undefined" ? [] : navigator.languages ?? [navigator.language]): string | null {
  for (const tag of languages) {
    const region = tag.split("-").find((part, i) => i > 0 && /^[A-Za-z]{2}$/.test(part));
    if (region) return region.toUpperCase();
  }
  return null;
}

export const localizationApi = {
  get: () => {
    const hint = browserRegion();
    return api.get<Localization>(`/localization${hint ? `?hint=${hint}` : ""}`);
  },
  setCartCurrency: (currency: string) => api.put<Cart>("/cart/currency", { currency }),
};
