"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef } from "react";
import { cartApi } from "@/features/cart/api";
import { cartKey, checkoutKey } from "@/features/cart/cart-context";
import type { Cart } from "@/features/cart/types";
import { localizationApi } from "../api";
import { useLocalization } from "../use-localization";

/**
 * Keeps the bag in the shopper's currency, so the server prices and charges it there.
 * Reads the bag only from the cache (never fetches it), and asks once per currency.
 */
export function CurrencySync() {
  const queryClient = useQueryClient();
  const { data, currency } = useLocalization();
  const { data: cart } = useQuery({ queryKey: cartKey, queryFn: cartApi.get, enabled: false });
  const asked = useRef<string | null>(null);

  useEffect(() => {
    if (!data?.enabled || !currency || !cart || cart.currency === currency || asked.current === currency) return;
    asked.current = currency;
    localizationApi.setCartCurrency(currency).then((fresh) => {
      queryClient.setQueryData(cartKey, fresh);
      queryClient.setQueryData(checkoutKey, (old: { cart: Cart } | undefined) => (old ? { ...old, cart: fresh } : old));
    }).catch(() => undefined);
  }, [data?.enabled, currency, cart, queryClient]);

  return null;
}
