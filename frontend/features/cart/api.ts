import { api } from "@/services/api-client";
import type { Address, Cart, Gateway, PlacedOrder } from "./types";

export const cartApi = {
  get: () => api.get<Cart>("/cart"),
  add: (variantUuid: string, quantity: number) => api.post<Cart>("/cart/items", { variant_uuid: variantUuid, quantity }),
  update: (itemUuid: string, quantity: number) => api.patch<Cart>(`/cart/items/${itemUuid}`, { quantity }),
  remove: (itemUuid: string) => api.delete<Cart>(`/cart/items/${itemUuid}`),
  applyCoupon: (code: string) => api.post<Cart>("/cart/coupon", { code }),
  removeCoupon: () => api.delete<Cart>("/cart/coupon"),
};

export const checkoutApi = {
  /** `saved_address`: the signed-in customer's last delivery address, offered for confirmation. */
  get: () => api.get<{ cart: Cart; gateways: Gateway[]; saved_address: Address | null }>("/checkout"),
  contact: (email: string, phone: string | null) => api.put<Cart>("/checkout/contact", { email, phone }),
  address: (address: Address) => api.put<Cart>("/checkout/address", address),
  shippingMethod: (code: string) => api.put<Cart>("/checkout/shipping-method", { code }),
  placeOrder: (gateway: string, expectedTotal: number, idempotencyKey: string) =>
    api.post<PlacedOrder>("/checkout/place-order", { gateway, expected_total: expectedTotal }, { headers: { "Idempotency-Key": idempotencyKey } }),
  confirmPayment: (orderNumber: string, data: Record<string, unknown>, token: string | null) =>
    api.post<{ status: string; payment_status: string }>(`/orders/${orderNumber}/payment/confirm`, data, {
      headers: { "Idempotency-Key": crypto.randomUUID(), ...(token ? { "X-Order-Token": token } : {}) },
    }),
  retryPayment: (orderNumber: string, gateway: string, token: string | null) =>
    api.post<{ gateway: string; client_payload: Record<string, unknown> }>(`/orders/${orderNumber}/payment/retry`, { gateway }, { headers: token ? { "X-Order-Token": token } : {} }),
};
