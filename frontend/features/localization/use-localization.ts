"use client";

import { useQuery } from "@tanstack/react-query";
import { useCallback, useSyncExternalStore } from "react";
import { convertMoney, formatMoney } from "@/lib/money";
import type { Money } from "@/types/api";
import { localizationApi, localizationKey } from "./api";
import { currencyStore } from "./currency-store";

const noop = () => () => {};

/** Visitor localization plus the currency every price is shown in. */
export function useLocalization() {
  const query = useQuery({ queryKey: localizationKey, queryFn: localizationApi.get, staleTime: 60 * 60_000 });
  const chosen = useSyncExternalStore(currencyStore.subscribe, currencyStore.getSnapshot, currencyStore.getServerSnapshot);
  // Until this component has hydrated, render what the server rendered (store-currency prices).
  // Suspense sections hydrate late, when the rates may already be loaded.
  const hydrated = useSyncExternalStore(noop, () => true, () => false);
  const data = hydrated ? query.data : undefined;
  const offered = data?.currencies ?? [];
  const currency = data
    ? (offered.some((c) => c.code === chosen) ? chosen! : data.default_currency)
    : null;

  return { data, currency, setCurrency: currencyStore.set, isPending: query.isPending };
}

/**
 * Formats a price in the shopper's currency. Store-currency amounts (catalogue prices)
 * are converted at today's rate; amounts the API already converted (bag, checkout)
 * are shown as they are.
 */
export function useFormatMoney(): (money: Money) => string {
  const { data, currency } = useLocalization();

  return useCallback((money: Money) => {
    if (!data || !currency || money.currency !== data.store_currency || currency === data.store_currency) return formatMoney(money);
    const base = data.currencies.find((c) => c.code === data.store_currency);
    const target = data.currencies.find((c) => c.code === currency);
    return base && target ? formatMoney(convertMoney(money, base.exponent, target)) : formatMoney(money);
  }, [data, currency]);
}
