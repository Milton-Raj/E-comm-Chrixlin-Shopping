import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { describe, expect, it, vi } from "vitest";
import { cartApi } from "./api";
import { CartProvider, cartKey, checkoutKey, useCartMutations } from "./cart-context";
import type { Cart } from "./types";

const cart = (total: number, items: string[], coupon: string | null) =>
  ({ items: items.map((uuid) => ({ uuid })), coupon: coupon ? { code: coupon } : null, totals: { total: { amount: total, currency: "INR" } } }) as unknown as Cart;

function setup() {
  const client = new QueryClient();
  client.setQueryData(cartKey, cart(359720, ["a", "b"], null));
  client.setQueryData(checkoutKey, { cart: cart(359720, ["a", "b"], null), gateways: [], saved_address: null });
  const wrapper = ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}><CartProvider>{children}</CartProvider></QueryClientProvider>;
  const { result } = renderHook(() => useCartMutations(), { wrapper });
  const checkoutCart = () => client.getQueryData<{ cart: Cart }>(checkoutKey)?.cart;
  return { result, checkoutCart };
}

describe("cart mutations on the checkout page", () => {
  it("shows an applied coupon in the order summary", async () => {
    vi.spyOn(cartApi, "applyCoupon").mockResolvedValue(cart(323748, ["a", "b"], "EXAMPLE15"));
    const { result, checkoutCart } = setup();
    act(() => result.current.applyCoupon.mutate("example15"));
    await waitFor(() => expect(checkoutCart()?.coupon?.code).toBe("EXAMPLE15"));
    expect(checkoutCart()?.totals.total.amount).toBe(323748);
  });

  it("drops a removed product and re-prices the order summary", async () => {
    vi.spyOn(cartApi, "remove").mockResolvedValue(cart(269820, ["b"], null));
    const { result, checkoutCart } = setup();
    act(() => result.current.remove.mutate("a"));
    await waitFor(() => expect(checkoutCart()?.items).toHaveLength(1));
    expect(checkoutCart()?.totals.total.amount).toBe(269820);
  });
});
