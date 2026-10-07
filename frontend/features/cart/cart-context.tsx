"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createContext, use, useState, type ReactNode } from "react";
import { toast } from "sonner";
import { ApiError } from "@/services/api-client";
import { cartApi } from "./api";
import type { Cart } from "./types";

export const cartKey = ["cart"] as const;

type CartContextValue = {
  drawerOpen: boolean;
  setDrawerOpen: (open: boolean) => void;
};

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const [drawerOpen, setDrawerOpen] = useState(false);
  return <CartContext value={{ drawerOpen, setDrawerOpen }}>{children}</CartContext>;
}

export function useCartDrawer(): CartContextValue {
  const value = use(CartContext);
  if (!value) throw new Error("useCartDrawer must be used inside CartProvider");
  return value;
}

export function useCart() {
  return useQuery({ queryKey: cartKey, queryFn: cartApi.get, staleTime: 15_000 });
}

function errorMessage(error: unknown): string {
  return error instanceof ApiError ? (Object.values(error.fieldErrors)[0]?.[0] ?? error.message) : "Something went wrong. Please try again.";
}

/** All cart mutations write the server's priced cart straight into the query cache. */
export function useCartMutations() {
  const queryClient = useQueryClient();
  const { setDrawerOpen } = useCartDrawer();
  const store = (cart: Cart) => queryClient.setQueryData(cartKey, cart);
  const onError = (error: unknown) => toast.error(errorMessage(error));

  return {
    add: useMutation({
      mutationFn: ({ variantUuid, quantity }: { variantUuid: string; quantity: number; openDrawer?: boolean }) => cartApi.add(variantUuid, quantity),
      onSuccess: (cart, variables) => {
        store(cart);
        if (variables.openDrawer !== false) setDrawerOpen(true);
      },
      onError,
    }),
    update: useMutation({ mutationFn: ({ item, quantity }: { item: string; quantity: number }) => cartApi.update(item, quantity), onSuccess: store, onError }),
    remove: useMutation({ mutationFn: (item: string) => cartApi.remove(item), onSuccess: store, onError }),
    applyCoupon: useMutation({ mutationFn: (code: string) => cartApi.applyCoupon(code), onSuccess: store }),
    removeCoupon: useMutation({ mutationFn: () => cartApi.removeCoupon(), onSuccess: store, onError }),
  };
}
