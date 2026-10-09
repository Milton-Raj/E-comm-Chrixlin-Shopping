"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { CheckCircle2, Package, Truck } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { ButtonLink } from "@/components/button-link";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { useCurrentUser } from "@/features/auth/hooks";
import { formatMoney } from "@/lib/money";
import { ApiError } from "@/services/api-client";
import { ordersApi } from "../api";
import { DownloadButton } from "./download-button";
import { InvoiceButton } from "./invoice-button";

const steps = ["paid", "processing", "shipped", "delivered"];

export function OrderView({ orderNumber, token, confirmation = false }: { orderNumber: string; token?: string | null; confirmation?: boolean }) {
  const queryClient = useQueryClient();
  const { data: user } = useCurrentUser();
  const query = useQuery({ queryKey: ["order", orderNumber], queryFn: () => ordersApi.get(orderNumber, token) });

  if (query.isPending) return <LoadingState lines={8} />;
  if (query.isError) {
    const notFound = query.error instanceof ApiError && query.error.status === 404;
    return (
      <ErrorState
        title={notFound ? "Order not found" : undefined}
        description={notFound ? "Sign in with the account used to place this order, or open the link from your confirmation email." : undefined}
        requestId={query.error instanceof ApiError ? query.error.requestId : null}
        onRetry={notFound ? undefined : () => void query.refetch()}
      />
    );
  }

  const order = query.data;
  const paid = ["captured", "partially_refunded", "refunded"].includes(order.payment_status);
  const physical = order.items.some((i) => i.product_type === "physical");
  const currentStep = steps.indexOf(order.status === "out_for_delivery" ? "shipped" : order.status);

  return (
    <div className="grid gap-10">
      {confirmation ? (
        <header className="grid justify-items-center gap-4 border border-border bg-card px-6 py-10 text-center">
          <CheckCircle2 className="size-10 text-primary" aria-hidden />
          <p className="eyebrow text-muted-foreground">{paid ? "Payment confirmed" : "Order received"}</p>
          <h1 className="font-display-tight text-4xl md:text-5xl">Thank you for your order</h1>
          <p className="max-w-lg text-muted-foreground">
            Order <strong className="text-foreground">{order.order_number}</strong> · a confirmation has been sent to {order.email}
            {order.invoice_number ? <> with your tax invoice <strong className="text-foreground">{order.invoice_number}</strong> attached</> : null}.
            {order.downloads.length ? " Your digital items are ready to download below." : ""}
          </p>
          {order.invoice_number ? <InvoiceButton orderNumber={order.order_number} invoiceNumber={order.invoice_number} token={token} /> : null}
          {!user ? (
            <div className="mt-2 grid gap-2">
              <p className="text-sm text-muted-foreground">Create an account to track orders and keep your downloads in one place.</p>
              <ButtonLink href="/register" variant="outline">Create an account</ButtonLink>
            </div>
          ) : null}
        </header>
      ) : (
        <header className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="eyebrow text-muted-foreground">Order</p>
            <h1 className="font-display-tight text-4xl md:text-5xl">{order.order_number}</h1>
            <p className="mt-2 text-sm text-muted-foreground">Placed {order.placed_at ? new Date(order.placed_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : ""}</p>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            {order.invoice_number ? <InvoiceButton orderNumber={order.order_number} invoiceNumber={order.invoice_number} token={token} /> : null}
            <span className="eyebrow border border-foreground px-3 py-1.5">{order.status_label}</span>
          </div>
        </header>
      )}

      {physical && paid && currentStep >= 0 ? (
        <ol className="grid grid-cols-4 gap-2" aria-label="Delivery progress">
          {steps.map((s, i) => (
            <li key={s} className="grid gap-2">
              <span className={`h-1 ${i <= currentStep ? "bg-primary" : "bg-border"}`} />
              <span className={`eyebrow ${i <= currentStep ? "text-foreground" : "text-muted-foreground"}`}>{s}</span>
            </li>
          ))}
        </ol>
      ) : null}

      {order.shipments.length ? (
        <section className="grid gap-2 border border-border p-5">
          <h2 className="flex items-center gap-2 text-xl font-semibold"><Truck className="size-5" aria-hidden /> Shipment</h2>
          {order.shipments.map((s, i) => (
            <p key={i} className="text-sm">
              {s.carrier} · <span className="font-mono">{s.tracking_number}</span>
              {s.tracking_url ? <> · <a className="underline underline-offset-4" href={s.tracking_url} target="_blank" rel="noopener noreferrer">Track</a></> : null}
            </p>
          ))}
        </section>
      ) : null}

      {order.downloads.length ? (
        <section className="grid gap-4 border border-border p-5" aria-labelledby="downloads-heading">
          <h2 id="downloads-heading" className="text-xl font-semibold">Your downloads</h2>
          {order.downloads.map((d) => (
            <div key={d.uuid} className="grid gap-3 border-t border-border pt-4 first:border-0 first:pt-0">
              <p className="font-medium">{d.product?.name}</p>
              <p className="text-sm text-muted-foreground">
                {d.download_limit ? `${d.downloads_used} of ${d.download_limit} downloads used` : `${d.downloads_used} downloads`}
                {d.expires_at ? ` · access until ${new Date(d.expires_at).toLocaleDateString("en-IN")}` : " · lifetime access"}
                {!d.usable ? " · no longer available" : ""}
              </p>
              <div className="flex flex-wrap gap-2">
                {d.files.map((f) => (
                  <DownloadButton key={f.uuid} entitlement={d.uuid} file={f.uuid} name={f.name} disabled={!d.usable} token={token} onDownloaded={() => setTimeout(() => void queryClient.invalidateQueries({ queryKey: ["order", orderNumber] }), 1500)} />
                ))}
              </div>
            </div>
          ))}
        </section>
      ) : null}

      <div className="grid gap-10 lg:grid-cols-3">
        <section className="lg:col-span-2" aria-labelledby="items-heading">
          <h2 id="items-heading" className="text-xl font-semibold">Items</h2>
          <ul className="mt-4 divide-y divide-border border-y border-border">
            {order.items.map((item) => (
              <li key={item.uuid} className="flex gap-4 py-4">
                <div className="relative aspect-4/5 w-16 shrink-0 overflow-hidden bg-muted">
                  {item.image ? <Image src={item.image} alt="" fill sizes="64px" className="object-cover" /> : <Package className="m-auto size-6 text-muted-foreground" aria-hidden />}
                </div>
                <div className="min-w-0 flex-1">
                  {item.product_slug ? <Link href={`/${item.product_type === "digital" ? "digital" : "product"}/${item.product_slug}`} className="font-medium hover:text-primary">{item.name}</Link> : <p className="font-medium">{item.name}</p>}
                  {item.variant_name ? <p className="text-sm text-muted-foreground">{item.variant_name}</p> : null}
                  <p className="text-sm text-muted-foreground">{item.quantity} × {formatMoney(item.unit_price)}</p>
                </div>
                <p className="text-sm font-medium">{formatMoney(item.line_total)}</p>
              </li>
            ))}
          </ul>
        </section>
        <aside className="grid content-start gap-6">
          <div className="grid gap-2 border border-border p-5 text-sm">
          <dl className="grid gap-2">
            <Row label="Subtotal" value={formatMoney(order.totals.subtotal)} />
            {order.totals.discount.amount ? <Row label={`Discount${order.coupon_code ? ` (${order.coupon_code})` : ""}`} value={`− ${formatMoney(order.totals.discount)}`} /> : null}
            {physical ? <Row label="Delivery" value={order.totals.shipping.amount ? formatMoney(order.totals.shipping) : "Free"} /> : null}
            <div className="mt-2 flex justify-between border-t border-border pt-3 text-base"><dt>Total</dt><dd className="text-xl font-semibold">{formatMoney(order.totals.total)}</dd></div>
            {order.totals.refunded.amount ? <Row label="Refunded" value={formatMoney(order.totals.refunded)} /> : null}
          </dl>
            {order.totals.tax.amount ? <p className="text-xs text-muted-foreground">Includes {formatMoney(order.totals.tax)} GST ({order.tax_breakdown.map((t) => `${t.code} ${formatMoney(t.amount)}`).join(", ")})</p> : null}
          </div>
          {order.shipping_address ? (
            <div className="border border-border p-5 text-sm">
              <p className="eyebrow mb-2">Delivering to</p>
              <p>{order.shipping_address.name}</p>
              <p className="text-muted-foreground">{order.shipping_address.line1}{order.shipping_address.line2 ? `, ${order.shipping_address.line2}` : ""}</p>
              <p className="text-muted-foreground">{order.shipping_address.city} {order.shipping_address.postal_code}, {order.shipping_address.state_code}, {order.shipping_address.country_code}</p>
            </div>
          ) : null}
          {user ? <ButtonLink href="/orders" variant="outline">All orders</ButtonLink> : <ButtonLink href="/shop" variant="outline">Continue shopping</ButtonLink>}
        </aside>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between gap-4"><dt className="text-muted-foreground">{label}</dt><dd>{value}</dd></div>;
}
