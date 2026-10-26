"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { formatMoney } from "@/lib/money";
import { miscAdminApi } from "../api";
import { AdminPage, Panel, StatusBadge } from "../components/kit/admin-page";

const when = (iso: string | null) => (iso ? new Date(iso).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" }) : "—");

/** Admin → Marketing: abandoned bags and what shoppers are waiting to be restocked. */
export function MarketingPage() {
  return (
    <AdminPage title="Marketing" description="Bags shoppers left behind and sold-out items they asked about. Switch features on or off in Settings → Marketing.">
      <div className="grid items-start gap-4">
        <AbandonedBags />
        <WaitingForRestock />
      </div>
    </AdminPage>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return <div className="border border-border p-3"><p className="text-xs text-muted-foreground">{label}</p><p className="text-xl font-semibold">{value}</p></div>;
}

function AbandonedBags() {
  const q = useQuery({ queryKey: ["admin", "marketing", "abandoned"], queryFn: miscAdminApi.abandonedCarts });
  return (
    <Panel title="Abandoned bags">
      {q.isPending ? <LoadingState lines={4} /> : q.isError ? <ErrorState onRetry={() => void q.refetch()} /> : (
        <>
          <p className="text-sm text-muted-foreground">
            {q.data.enabled
              ? `Reminder emails are on: one email ${q.data.delay_hours} hour${q.data.delay_hours === 1 ? "" : "s"} after a shopper with an email leaves their bag.`
              : <>Reminder emails are off. <Link href="/admin/settings" className="underline underline-offset-4">Turn them on in Settings → Marketing</Link>.</>}
          </p>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Stat label="Reminders sent (30 days)" value={q.data.reminders_sent_30d} />
            <Stat label="Came back and ordered" value={q.data.recovered_30d} />
            <Stat label="Recovery rate" value={q.data.reminders_sent_30d ? `${Math.round((q.data.recovered_30d / q.data.reminders_sent_30d) * 100)}%` : "—"} />
          </div>
          {q.data.carts.length === 0 ? <p className="text-sm text-muted-foreground">No abandoned bags in the last 30 days.</p> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground uppercase"><tr><th className="p-2">Shopper</th><th className="p-2">Items</th><th className="p-2">Bag value</th><th className="p-2">Last active</th><th className="p-2">Reminder</th></tr></thead>
                <tbody className="divide-y divide-border">
                  {q.data.carts.map((c) => (
                    <tr key={c.uuid}>
                      <td className="p-2">{c.customer ? <span className="block font-medium">{c.customer}</span> : null}<span className="text-muted-foreground">{c.email ?? "No email (guest)"}</span></td>
                      <td className="p-2">{c.items}</td>
                      <td className="p-2 whitespace-nowrap">{formatMoney(c.value)}</td>
                      <td className="p-2 whitespace-nowrap">{when(c.last_activity_at)}</td>
                      <td className="p-2">{c.reminder_sent_at ? <span className="whitespace-nowrap">Sent {when(c.reminder_sent_at)}</span> : c.email ? <StatusBadge value="pending" /> : "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </Panel>
  );
}

function WaitingForRestock() {
  const q = useQuery({ queryKey: ["admin", "marketing", "stock-alerts"], queryFn: miscAdminApi.stockAlerts });
  return (
    <Panel title="Waiting for restock">
      {q.isPending ? <LoadingState lines={4} /> : q.isError ? <ErrorState onRetry={() => void q.refetch()} /> : (
        <>
          <p className="text-sm text-muted-foreground">Shoppers who tapped “Notify me” on a sold-out item. Each gets one email within minutes of you adding stock in Inventory. {q.data.notified_last_30_days} sent in the last 30 days.</p>
          {q.data.waiting.length === 0 ? <p className="text-sm text-muted-foreground">Nobody is waiting right now.</p> : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground uppercase"><tr><th className="p-2">Product</th><th className="p-2">Waiting</th><th className="p-2">In stock now</th><th className="p-2">Last request</th></tr></thead>
                <tbody className="divide-y divide-border">
                  {q.data.waiting.map((w, i) => (
                    <tr key={`${w.variant?.sku ?? i}`}>
                      <td className="p-2">
                        {w.product ? <Link href={`/admin/products/${w.product.slug}`} className="font-medium underline-offset-4 hover:underline">{w.product.name}</Link> : "Removed product"}
                        {w.variant ? <span className="block text-xs text-muted-foreground">{[w.variant.name, w.variant.sku].filter(Boolean).join(" · ")}</span> : null}
                      </td>
                      <td className="p-2 font-semibold">{w.waiting}</td>
                      <td className="p-2">{w.available ?? "—"}</td>
                      <td className="p-2 whitespace-nowrap">{when(w.last_requested_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </>
      )}
    </Panel>
  );
}
