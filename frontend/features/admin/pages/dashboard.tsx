"use client";

import { useQuery } from "@tanstack/react-query";
import { AlertTriangle, ArrowDownRight, ArrowUpRight, Minus } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { adminApi, type Dashboard } from "../api";
import { bucketLabel, formatCount, formatInr, formatInrCompact, RankedBars, SplitBar, TimeColumns, TimeLine } from "../components/charts";
import { AdminPage, Panel, StatusBadge } from "../components/kit/admin-page";

const ranges = [
  { days: 7, label: "7 days" },
  { days: 30, label: "30 days" },
  { days: 90, label: "90 days" },
  { days: 365, label: "12 months" },
] as const;

export function DashboardPage() {
  const [range, setRange] = useState<number>(30);
  const query = useQuery({ queryKey: ["admin", "dashboard", range], queryFn: () => adminApi.dashboard(range), placeholderData: (p) => p });

  return (
    <AdminPage
      title="Dashboard"
      description="How the store is performing. Every figure compares with the previous period of the same length."
      actions={
        <div className="inline-flex rounded-sm border border-border bg-card p-1" role="group" aria-label="Date range">
          {ranges.map((r) => (
            <button key={r.days} type="button" aria-pressed={range === r.days} onClick={() => setRange(r.days)}
              className={cn("min-h-9 rounded-sm px-3 text-sm font-medium", range === r.days ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:text-foreground")}>
              {r.label}
            </button>
          ))}
        </div>
      }
    >
      {query.isPending ? <LoadingState lines={10} /> : query.isError ? <ErrorState onRetry={() => void query.refetch()} /> : <DashboardBody d={query.data} />}
    </AdminPage>
  );
}

function DashboardBody({ d }: { d: Dashboard }) {
  const period = d.range.days === 365 ? "previous 12 months" : `previous ${d.range.days} days`;
  const unit = d.range.bucket === "month" ? "month" : "day";
  const revenueTotal = d.series.reduce((s, p) => s + p.revenue, 0);
  const best = d.series.reduce((b, p) => (p.revenue > b.revenue ? p : b), d.series[0] ?? { bucket: "", revenue: 0, orders: 0, new_customers: 0 });
  const ordersTotal = d.series.reduce((s, p) => s + p.orders, 0);
  const peakOrders = d.series.reduce((b, p) => (p.orders > b.orders ? p : b), d.series[0] ?? { bucket: "", revenue: 0, orders: 0, new_customers: 0 });
  const customersNew = d.series.reduce((s, p) => s + p.new_customers, 0);
  const statusTotal = d.orders_by_status.reduce((s, x) => s + x.count, 0);

  return (
    <div className="grid gap-5">
      {d.totals.needs_attention > 0 ? (
        <Link href="/admin/orders?attention=1" className="flex items-center gap-2 border-l-4 border-primary bg-primary/5 px-4 py-3 text-sm font-medium">
          <AlertTriangle className="size-4 text-primary" aria-hidden /> {d.totals.needs_attention} order(s) need attention (payment or stock mismatch). Review now →
        </Link>
      ) : null}

      <ul className="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Key figures">
        <Tile label="Revenue" value={formatMoney(d.kpis.revenue.current)} current={d.kpis.revenue.current.amount} previous={d.kpis.revenue.previous.amount} period={period} sub={`Today ${formatMoney(d.today.revenue)}`} />
        <Tile label="Paid orders" value={formatCount(d.kpis.orders.current)} current={d.kpis.orders.current} previous={d.kpis.orders.previous} period={period} sub={`Today ${d.today.orders}`} />
        <Tile label="Average order value" value={formatMoney(d.kpis.average_order_value.current)} current={d.kpis.average_order_value.current.amount} previous={d.kpis.average_order_value.previous.amount} period={period} />
        <Tile label="Units sold" value={formatCount(d.kpis.units_sold.current)} current={d.kpis.units_sold.current} previous={d.kpis.units_sold.previous} period={period} />
        <Tile label="New customers" value={formatCount(d.kpis.new_customers.current)} current={d.kpis.new_customers.current} previous={d.kpis.new_customers.previous} period={period} sub={`${formatCount(d.totals.customers)} customers in total`} />
        <Tile label="Checkout conversion" value={`${d.kpis.conversion.current}%`} current={d.kpis.conversion.current} previous={d.kpis.conversion.previous} period={period} sub="Orders placed → paid" />
        <Tile label="Refunds" value={formatMoney(d.kpis.refunds.current)} current={d.kpis.refunds.current.amount} previous={d.kpis.refunds.previous.amount} period={period} upIsGood={false} />
        <Tile label="Digital downloads" value={formatCount(d.kpis.downloads.current)} current={d.kpis.downloads.current} previous={d.kpis.downloads.previous} period={period} />
      </ul>

      <TimeColumns
        title={`Revenue per ${unit}`}
        summary={revenueTotal ? `${formatInr(revenueTotal)} in total · best ${unit} ${formatInr(best.revenue)} on ${bucketLabel(best.bucket, true)} · average ${formatInr(Math.round(revenueTotal / Math.max(1, d.series.length)))} per ${unit}` : "No paid orders in this period yet."}
        data={d.series} valueKey="revenue" valueName="Revenue" format={formatInr} axisFormat={formatInrCompact} height={300}
      />

      <div className="grid gap-5 lg:grid-cols-2">
        <TimeLine
          title={`Paid orders per ${unit}`}
          summary={ordersTotal ? `${formatCount(ordersTotal)} orders · busiest ${unit}: ${bucketLabel(peakOrders.bucket, true)} (${peakOrders.orders})` : "No paid orders yet."}
          data={d.series} valueKey="orders" valueName="Orders" format={formatCount}
        />
        <TimeColumns
          title={`New customers per ${unit}`}
          summary={`${formatCount(customersNew)} new sign-ups · ${formatCount(d.totals.customers)} customers and ${formatCount(d.totals.staff)} staff in total`}
          data={d.series} valueKey="new_customers" valueName="New customers" format={formatCount} axisFormat={formatCount} height={240}
        />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <RankedBars
          title="Best-selling products"
          summary="Units sold, with revenue and the change against the previous period."
          data={d.top_products.map((p) => ({ label: p.name, value: p.units, note: `${formatInrCompact(p.revenue.amount)} · ${trendText(p.units, p.previous_units)}` }))}
          valueName="Units" format={(v) => `${formatCount(v)} sold`}
        />
        <RankedBars
          title="Revenue by category"
          summary={`${d.by_category.length} categories with sales`}
          data={d.by_category.map((c) => ({ label: c.category, value: c.revenue.amount, note: `${formatCount(c.units)} units` }))}
          valueName="Revenue" format={formatInrCompact}
        />
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <RankedBars
          title="Orders by status"
          summary={`${formatCount(statusTotal)} orders placed in this period`}
          data={d.orders_by_status.map((s) => ({ label: s.status.replaceAll("_", " ").replace(/^./, (c) => c.toUpperCase()), value: s.count }))}
          valueName="Orders" format={formatCount}
        />
        <div className="grid content-start gap-5">
          <SplitBar
            title="Physical vs digital sales"
            parts={d.by_type.map((t) => ({ label: t.type === "physical" ? "Physical" : "Digital", value: t.revenue.amount, sub: `${formatCount(t.units)} units` }))}
            format={formatInr}
          />
          <ul className="grid grid-cols-2 gap-3">
            <MiniStat label="Active products" value={d.totals.active_products} sub={`${d.totals.digital_products} digital`} href="/admin/products?status=active" />
            <MiniStat label="Orders to ship" value={d.totals.orders_to_fulfil} sub="Status: processing" href="/admin/orders?status=processing" />
            <MiniStat label="Low-stock variants" value={d.totals.low_stock_variants} sub="At or below threshold" href="/admin/inventory?low=1" />
            <MiniStat label="Staff accounts" value={d.totals.staff} sub="With admin roles" />
          </ul>
        </div>
      </div>

      <div className="grid gap-5 lg:grid-cols-2">
        <Panel title="Low stock" actions={<Link href="/admin/inventory?low=1" className="text-sm underline underline-offset-4">Inventory</Link>}>
          {d.low_stock.length ? (
            <table className="w-full text-sm">
              <thead className="text-left text-xs font-semibold text-muted-foreground uppercase"><tr><th className="py-1.5">Product</th><th className="py-1.5">SKU</th><th className="py-1.5 text-right">Available</th></tr></thead>
              <tbody className="divide-y divide-border">
                {d.low_stock.map((s) => (
                  <tr key={s.sku}>
                    <td className="py-2"><Link className="hover:text-primary" href={`/admin/products/${s.product_uuid}`}>{s.name}</Link>{s.variant ? <span className="text-muted-foreground"> · {s.variant}</span> : null}</td>
                    <td className="py-2 font-mono text-xs">{s.sku}</td>
                    <td className={cn("py-2 text-right font-semibold tabular-nums", s.available === 0 && "text-red-700")}>{s.available === 0 ? "Sold out" : s.available}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          ) : <p className="text-sm text-muted-foreground">Everything is well stocked.</p>}
        </Panel>
        <Panel title="Recent orders" actions={<Link href="/admin/orders" className="text-sm underline underline-offset-4">All orders</Link>}>
          <table className="w-full text-sm">
            <thead className="text-left text-xs font-semibold text-muted-foreground uppercase"><tr><th className="py-1.5">Order</th><th className="py-1.5">Status</th><th className="py-1.5 text-right">Total</th></tr></thead>
            <tbody className="divide-y divide-border">
              {d.recent_orders.map((o) => (
                <tr key={o.order_number}>
                  <td className="py-2"><Link href={`/admin/orders/${o.order_number}`} className="font-medium hover:text-primary">{o.order_number}</Link><span className="block max-w-56 truncate text-xs text-muted-foreground">{o.email}</span></td>
                  <td className="py-2"><StatusBadge value={o.status} /></td>
                  <td className="py-2 text-right tabular-nums">{formatMoney(o.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
      </div>
    </div>
  );
}

function trendText(current: number, previous: number): string {
  if (previous === 0) return current > 0 ? "new" : "–";
  const change = Math.round(((current - previous) / previous) * 100);
  return change === 0 ? "flat" : `${change > 0 ? "▲" : "▼"} ${Math.abs(change)}%`;
}

function Tile({ label, value, current, previous, period, sub, upIsGood = true }: { label: string; value: string; current: number; previous: number; period: string; sub?: string; upIsGood?: boolean }) {
  const change = previous === 0 ? (current > 0 ? null : 0) : ((current - previous) / previous) * 100;
  const up = change === null ? current > 0 : change > 0; // from zero to something counts as a rise
  const flat = change === 0;
  const good = flat ? null : up === upIsGood;
  const Icon = flat ? Minus : up ? ArrowUpRight : ArrowDownRight;

  return (
    <li className="grid content-start gap-1 border border-border bg-card p-4">
      <p className="text-sm font-medium text-muted-foreground">{label}</p>
      <p className="text-2xl font-semibold tracking-tight md:text-3xl">{value}</p>
      <p className={cn("flex items-center gap-1 text-xs font-medium", good === null ? "text-muted-foreground" : good ? "text-emerald-700" : "text-red-700")}>
        <Icon className="size-3.5" aria-hidden />
        {change === null ? "New this period" : flat ? "No change" : `${up ? "+" : "−"}${Math.abs(change).toFixed(change !== 0 && Math.abs(change) < 10 ? 1 : 0)}%`}
        <span className="font-normal text-muted-foreground">vs {period}</span>
      </p>
      {sub ? <p className="text-xs text-muted-foreground">{sub}</p> : null}
    </li>
  );
}

function MiniStat({ label, value, sub, href }: { label: string; value: number; sub: string; href?: string }) {
  const body = (
    <>
      <p className="text-sm font-medium text-muted-foreground">{label}</p>
      <p className="text-2xl font-semibold">{formatCount(value)}</p>
      <p className="text-xs text-muted-foreground">{sub}</p>
    </>
  );
  return <li className="border border-border bg-card p-4">{href ? <Link href={href} className="block hover:text-primary">{body}</Link> : body}</li>;
}
