"use client";

import { cn } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { formatMoney } from "@/lib/money";
import { fromMinorUnits, toMinorUnits } from "@/lib/money-input";
import { ApiError } from "@/services/api-client";
import type { OrderSummary } from "@/features/orders/types";
import { ordersAdminApi, type AdminOrderDetail } from "../api";
import { CourierPanel, nextStaffStage, StageTracker, stageLabel } from "../components/order-stages";
import { ExportButton } from "../components/export-button";
import { AdminPage, checkboxLabelClass, Field, FilterBar, FormActions, inputClass, Pagination, Panel, StatusBadge } from "../components/kit/admin-page";

const statuses = ["pending", "paid", "processing", "packed", "shipped", "out_for_delivery", "delivered", "cancelled", "refunded", "partially_refunded"];

export function OrdersPage() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const query = params.toString();
  const [q, setQ] = useState(params.get("q") ?? "");
  const orders = useQuery({ queryKey: ["admin", "orders", query], queryFn: () => ordersAdminApi.list(query ? `?${query}` : ""), placeholderData: (p) => p });

  const set = (key: string, value: string | null) => {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(key, value); else next.delete(key);
    if (key !== "page") next.delete("page");
    router.replace(`${pathname}${next.size ? `?${next}` : ""}`);
  };

  return (
    <AdminPage title="Orders" description="Every order, its payment and fulfilment state." actions={<ExportButton type="orders" />}>
      <div className="-mx-1 flex gap-1 overflow-x-auto pb-1">
        {[null, ...statuses].map((s) => (
          <button key={s ?? "all"} type="button" onClick={() => set("status", s)} className={`min-h-10 whitespace-nowrap rounded-sm border px-3 text-sm capitalize ${(params.get("status") ?? null) === s ? "border-foreground bg-foreground text-background" : "border-border"}`}>
            {s ? s.replaceAll("_", " ") : "All"}
          </button>
        ))}
      </div>
      <FilterBar>
        <form role="search" onSubmit={(e) => { e.preventDefault(); set("q", q.trim() || null); }}>
          <input aria-label="Search orders" className={inputClass} placeholder="Order number or email, press Enter" value={q} onChange={(e) => setQ(e.target.value)} />
        </form>
      </FilterBar>
      {orders.isPending ? <LoadingState lines={8} /> : orders.isError ? <ErrorState onRetry={() => void orders.refetch()} /> : !orders.data.items.length ? <EmptyState title="No orders match" /> : (
        <>
          <div className="overflow-x-auto border border-border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b border-border text-left text-xs text-muted-foreground uppercase"><tr><th className="p-3">Order</th><th className="p-3">Placed</th><th className="p-3">Customer</th><th className="p-3">Status</th><th className="p-3">Payment</th><th className="p-3 text-right">Total</th><th className="p-3">Next step</th></tr></thead>
              <tbody className="divide-y divide-border">
                {orders.data.items.map((o) => (
                  <tr key={o.uuid} className="hover:bg-muted/40">
                    <td className="p-3"><Link href={`/admin/orders/${o.order_number}`} className="font-medium hover:text-primary">{o.order_number}</Link></td>
                    <td className="p-3 whitespace-nowrap text-muted-foreground">{o.placed_at ? new Date(o.placed_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : ""}</td>
                    <td className="p-3">{o.email}</td>
                    <td className="p-3"><StatusBadge value={o.status} /></td>
                    <td className="p-3"><StatusBadge value={o.payment_status} /></td>
                    <td className="p-3 text-right whitespace-nowrap">{formatMoney(o.totals.total)}</td>
                    <td className="p-3 whitespace-nowrap"><NextStep order={o} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={orders.data.pagination.page} lastPage={orders.data.pagination.last_page} onChange={(n) => set("page", String(n))} />
        </>
      )}
    </AdminPage>
  );
}

/** One-click move to the next stage from the list; stages that need details link to the order. */
function NextStep({ order }: { order: OrderSummary }) {
  const queryClient = useQueryClient();
  const next = nextStaffStage(order.status, order.requires_shipping ?? true);
  const move = useMutation({
    mutationFn: (to: string) => ordersAdminApi.status(order.order_number, to),
    onSuccess: (o, to) => { void queryClient.invalidateQueries({ queryKey: ["admin", "orders"] }); courierToast(o, to); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not update the order."),
  });
  if (next) {
    return <Button size="sm" variant="outline" disabled={move.isPending} onClick={() => move.mutate(next)}>Mark {stageLabel(next).toLowerCase()}</Button>;
  }
  if (order.status === "packed") {
    return <Link href={`/admin/orders/${order.order_number}`} className="text-sm underline underline-offset-4">Ship / track</Link>;
  }
  return <span className="text-sm text-muted-foreground">—</span>;
}

/** After a stage change: report the Shiprocket booking result when packing booked a pickup. */
function courierToast(o: AdminOrderDetail, to: string) {
  const courier = o.courier_shipments.at(-1);
  if (to === "packed" && o.courier_enabled && courier?.status === "failed") toast.error(`Marked packed, but the Shiprocket booking failed: ${courier.last_error ?? "unknown error"}`);
  else if (to === "packed" && o.courier_enabled && courier?.status === "pickup_scheduled") toast.success(`Marked packed. Pickup booked with ${courier.carrier} (AWB ${courier.tracking_number}).`);
  else toast.success(`${o.order_number} marked ${stageLabel(o.status).toLowerCase()}.`);
}

export function OrderDetailPage({ orderNumber }: { orderNumber: string }) {
  const queryClient = useQueryClient();
  const order = useQuery({ queryKey: ["admin", "order", orderNumber], queryFn: () => ordersAdminApi.get(orderNumber) });
  const store = (o: AdminOrderDetail) => { queryClient.setQueryData(["admin", "order", orderNumber], o); void queryClient.invalidateQueries({ queryKey: ["admin", "orders"] }); };
  const onError = (e: unknown) => toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Action failed.");
  const transition = useMutation({ mutationFn: (to: string) => ordersAdminApi.status(orderNumber, to), onSuccess: (o, to) => { store(o); courierToast(o, to); }, onError });

  if (order.isPending) return <LoadingState lines={10} />;
  if (order.isError) return <ErrorState onRetry={() => void order.refetch()} />;
  const o = order.data;
  const paid = o.payment_status === "captured" || o.payment_status === "partially_refunded";
  const physical = o.items.some((i) => i.product_type === "physical");

  return (
    <AdminPage
      title={o.order_number}
      description={`Placed ${o.placed_at ? new Date(o.placed_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : ""} · ${o.email}`}
      actions={<><StatusBadge value={o.status} /><StatusBadge value={o.payment_status} /></>}
    >
      {o.requires_attention ? <p className="border-l-4 border-primary bg-primary/5 px-4 py-3 text-sm">This order needs attention: the payment arrived after the order expired, the amount did not match, or stock was insufficient. Review payments below and refund or fulfil manually.</p> : null}
      {paid || o.status === "delivered" ? <StageTracker order={o} pending={transition.isPending} onMove={(s) => transition.mutate(s)} /> : null}
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="grid content-start gap-4 lg:col-span-2">
          <Panel title="Items">
            <ul className="divide-y divide-border text-sm">
              {o.items.map((i) => (
                <li key={i.uuid} className="flex justify-between gap-3 py-2">
                  <span><span className="font-medium">{i.name}</span>{i.variant_name ? ` · ${i.variant_name}` : ""} <span className="text-muted-foreground">({i.sku}) × {i.quantity}</span>{i.product_type === "digital" ? <span className="ml-2"><StatusBadge value="digital" /></span> : null}</span>
                  <span>{formatMoney(i.line_total)}</span>
                </li>
              ))}
            </ul>
            <dl className="grid gap-1 border-t border-border pt-3 text-sm">
              <Row label="Subtotal" value={formatMoney(o.totals.subtotal)} />
              {o.totals.discount.amount ? <Row label={`Discount ${o.coupon_code ?? ""}`} value={`− ${formatMoney(o.totals.discount)}`} /> : null}
              <Row label="Shipping" value={formatMoney(o.totals.shipping)} />
              <Row label={`GST (incl.) ${o.tax_breakdown.map((t) => t.code).join("+")}`} value={formatMoney(o.totals.tax)} />
              <Row label="Total" value={formatMoney(o.totals.total)} strong />
              {o.totals.refunded.amount ? <Row label="Refunded" value={`− ${formatMoney(o.totals.refunded)}`} /> : null}
            </dl>
          </Panel>

          {physical ? <CourierPanel order={o} onSaved={store} onError={onError} /> : null}
          {physical && paid ? <ShipPanel order={o} onSaved={store} onError={onError} /> : null}
          {paid ? <RefundPanel order={o} onSaved={store} onError={onError} /> : null}

          <Panel title="Timeline">
            <ol className="grid gap-2 text-sm">
              {o.history.map((h, i) => (
                <li key={i} className="flex justify-between gap-3"><span><span className="capitalize">{h.to.replaceAll("_", " ")}</span> <span className="text-muted-foreground">· {h.actor}{h.reason ? ` · ${h.reason}` : ""}</span></span><span className="text-muted-foreground">{new Date(h.at).toLocaleString("en-IN", { dateStyle: "short", timeStyle: "short" })}</span></li>
              ))}
            </ol>
          </Panel>
        </div>

        <div className="grid content-start gap-4">
          {o.can_cancel ? <CancelPanel order={o} onSaved={store} onError={onError} /> : null}
          <Panel title="Customer">
            <p className="text-sm">{o.customer ? <Link className="underline underline-offset-4" href={`/admin/customers/${o.customer.uuid}`}>{o.customer.name}</Link> : "Guest checkout"}</p>
            <p className="text-sm text-muted-foreground">{o.email}{o.phone ? ` · ${o.phone}` : ""}</p>
            {o.shipping_address ? <p className="text-sm text-muted-foreground">{o.shipping_address.name}, {o.shipping_address.line1}, {o.shipping_address.city} {o.shipping_address.postal_code}, {o.shipping_address.state_code}</p> : null}
          </Panel>
          <Panel title="Payments">
            <ul className="grid gap-2 text-sm">
              {o.payments.map((p) => (
                <li key={p.uuid} className="flex justify-between gap-2"><span>{p.provider}{p.method ? ` · ${p.method}` : ""}<span className="block font-mono text-xs text-muted-foreground">{p.provider_payment_id ?? "—"}</span></span><span className="text-right">{formatMoney(p.amount)}<span className="block"><StatusBadge value={p.status} /></span></span></li>
              ))}
            </ul>
          </Panel>
          {o.courier_shipments.some((s) => !s.provider) ? (
            <Panel title="Shipments">
              {o.courier_shipments.filter((s) => !s.provider).map((s) => <p key={s.uuid} className="text-sm">{s.carrier} · <span className="font-mono">{s.tracking_number}</span></p>)}
            </Panel>
          ) : null}
          {o.downloads.length ? (
            <Panel title="Digital access">
              {o.downloads.map((d) => <p key={d.uuid} className="text-sm">{d.product?.name} · {d.downloads_used}/{d.download_limit ?? "∞"} · <StatusBadge value={d.status} /></p>)}
            </Panel>
          ) : null}
        </div>
      </div>
    </AdminPage>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return <div className={`flex justify-between ${strong ? "font-medium" : ""}`}><dt className="text-muted-foreground">{label}</dt><dd>{value}</dd></div>;
}

function ShipPanel({ order, onSaved, onError }: { order: AdminOrderDetail; onSaved: (o: AdminOrderDetail) => void; onError: (e: unknown) => void }) {
  const [carrier, setCarrier] = useState("Blue Dart");
  const [tracking, setTracking] = useState("");
  const [url, setUrl] = useState("");
  const ship = useMutation({ mutationFn: () => ordersAdminApi.ship(order.order_number, { carrier, tracking_number: tracking, tracking_url: url || undefined }), onSuccess: (o) => { onSaved(o); toast.success("Marked as shipped. The customer can now track it."); setTracking(""); }, onError });
  const courierActive = order.courier_shipments.some((s) => s.provider === "shiprocket" && s.status !== "failed");
  if (courierActive || (!order.allowed_transitions.includes("shipped") && order.status !== "paid")) return null;

  return (
    <Panel title={order.courier_enabled ? "Ship it yourself (without Shiprocket)" : "Ship order"}>
      <form className="grid items-start gap-4 md:grid-cols-3" onSubmit={(e) => { e.preventDefault(); ship.mutate(); }}>
        <Field label="Carrier" htmlFor="carrier" required><input id="carrier" className={inputClass} value={carrier} onChange={(e) => setCarrier(e.target.value)} required /></Field>
        <Field label="Tracking number" htmlFor="tracking" required><input id="tracking" className={inputClass} value={tracking} onChange={(e) => setTracking(e.target.value)} required /></Field>
        <Field label="Tracking URL" htmlFor="tracking-url"><input id="tracking-url" type="url" className={inputClass} placeholder="https://" value={url} onChange={(e) => setUrl(e.target.value)} /></Field>
        <FormActions className="md:col-span-3"><Button type="submit" disabled={ship.isPending || !carrier || !tracking}>Mark shipped</Button></FormActions>
      </form>
    </Panel>
  );
}

function RefundPanel({ order, onSaved, onError }: { order: AdminOrderDetail; onSaved: (o: AdminOrderDetail) => void; onError: (e: unknown) => void }) {
  const remaining = order.totals.total.amount - order.totals.refunded.amount;
  const [amount, setAmount] = useState(fromMinorUnits(remaining));
  const [reason, setReason] = useState("");
  const [restock, setRestock] = useState(true);
  const [confirming, setConfirming] = useState(false);
  const refund = useMutation({
    mutationFn: () => ordersAdminApi.refund(order.order_number, { amount: toMinorUnits(amount) ?? 0, reason, restock }),
    onSuccess: (o) => { onSaved(o); toast.success("Refund processed through the payment gateway."); setReason(""); setConfirming(false); },
    onError: (e) => { setConfirming(false); onError(e); },
  });
  if (remaining <= 0) return null;

  return (
    <Panel title="Refund">
      <form className="grid items-start gap-4 md:grid-cols-2" onSubmit={(e) => { e.preventDefault(); if (confirming) refund.mutate(); else setConfirming(true); }}>
        <Field label="Amount (₹)" htmlFor="refund-amount" required hint={`Up to ${formatMoney({ amount: remaining, currency: order.currency })}. A full refund revokes digital access.`}>
          <input id="refund-amount" inputMode="decimal" className={inputClass} value={amount} onChange={(e) => setAmount(e.target.value)} required />
        </Field>
        <Field label="Reason" htmlFor="refund-reason" required hint="Shown in the order timeline and audit log.">
          <input id="refund-reason" className={inputClass} placeholder="e.g. Damaged in transit" value={reason} onChange={(e) => setReason(e.target.value)} required />
        </Field>
        {order.items.some((i) => i.product_type === "physical") ? (
          <label className={cn(checkboxLabelClass, "md:col-span-2")}><input type="checkbox" className="size-4 accent-primary" checked={restock} onChange={(e) => setRestock(e.target.checked)} /> Return physical items to stock (full refund)</label>
        ) : null}
        <FormActions className="md:col-span-2">
          <Button type="submit" variant={confirming ? "default" : "outline"} disabled={refund.isPending || !reason.trim() || !amount}>{confirming ? `Confirm refund of ₹${amount}` : "Issue refund"}</Button>
          {confirming ? <Button type="button" variant="ghost" onClick={() => setConfirming(false)}>Cancel</Button> : null}
        </FormActions>
      </form>
    </Panel>
  );
}

function CancelPanel({ order, onSaved, onError }: { order: AdminOrderDetail; onSaved: (o: AdminOrderDetail) => void; onError: (e: unknown) => void }) {
  const cancel = useMutation({ mutationFn: () => ordersAdminApi.cancel(order.order_number, "Cancelled by staff"), onSuccess: (o) => { onSaved(o); toast.success("Order cancelled; reserved stock released."); }, onError });
  return (
    <Panel title="Unpaid order">
      <p className="text-sm text-muted-foreground">Cancelling releases reserved stock. Unpaid orders also expire automatically.</p>
      <div><Button size="sm" variant="outline" disabled={cancel.isPending} onClick={() => cancel.mutate()}>Cancel order</Button></div>
    </Panel>
  );
}
