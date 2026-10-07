import { api } from "@/services/api-client";
import type { Entitlement, OrderDetail, OrderSummary } from "./types";

const tokenHeader = (token?: string | null): Record<string, string> => (token ? { "X-Order-Token": token } : {});

export const ordersApi = {
  list: () => api.get<OrderSummary[]>("/orders"),
  get: (orderNumber: string, token?: string | null) => api.get<OrderDetail>(`/orders/${encodeURIComponent(orderNumber)}`, { headers: tokenHeader(token) }),
  cancel: (orderNumber: string, token?: string | null) => api.post(`/orders/${encodeURIComponent(orderNumber)}/cancel`, undefined, { headers: tokenHeader(token) }),
  downloads: () => api.get<Entitlement[]>("/account/downloads"),
  downloadLink: (entitlement: string, file: string, token?: string | null) =>
    api.post<{ url: string; expires_at: string }>(`/account/downloads/${entitlement}/files/${file}/link`, undefined, { headers: tokenHeader(token) }),
};
