"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { formatMoney } from "@/lib/money";
import { miscAdminApi } from "../api";
import { formatInr, formatInrCompact, TimeColumns } from "../components/charts";
import { adminMeKey } from "../components/admin-gate";
import { ExportButton } from "../components/export-button";
import { ByCurrency } from "./international";
import { ApiError } from "@/services/api-client";
import type { AdminMe } from "@/types/api";
import { AdminPage, Field, inputClass, Panel } from "../components/kit/admin-page";
import { currentFyStart, daysAgo, financialYears, fyLabel, fyRange, matchingFy, today } from "../lib/periods";
import { cn } from "@/lib/utils";

/** The store's first orders were in FY 2026–27; each April adds the new year. */
const FIRST_FY = 2026;

export function ReportsPage() {
  const [from, setFrom] = useState(() => daysAgo(29));
  const [to, setTo] = useState(today);
  const fy = matchingFy(from, to);
  const report = useQuery({ queryKey: ["admin", "sales", from, to], queryFn: () => miscAdminApi.sales(from, to), placeholderData: (p) => p });

  return (
    <AdminPage title="Reports" description="Sales, GST, products and categories for any period — a few days, a financial year or your own dates (paid orders, India time)." actions={<ExportButton type="report" from={from} to={to} label="Download full report (Excel)" />}>
      <div className="flex flex-wrap items-end gap-3">
        <Field label="From" htmlFor="r-from" className="w-44"><input id="r-from" type="date" className={inputClass} value={from} max={to} onChange={(e) => e.target.value && setFrom(e.target.value)} /></Field>
        <Field label="To" htmlFor="r-to" className="w-44"><input id="r-to" type="date" className={inputClass} value={to} min={from} onChange={(e) => e.target.value && setTo(e.target.value)} /></Field>
        <Field label="Financial year (April–March)" htmlFor="r-fy" className="w-56">
          <select id="r-fy" className={inputClass} value={fy ?? ""} onChange={(e) => { if (!e.target.value) return; const [f, t] = fyRange(Number(e.target.value)); setFrom(f); setTo(t); }}>
            <option value="">Custom dates</option>
            {financialYears(FIRST_FY).map((y) => <option key={y} value={y}>{fyLabel(y)}{y === currentFyStart() ? " (current)" : ""}</option>)}
          </select>
        </Field>
        <div className="flex flex-wrap gap-2">
          {[7, 30, 90, 365].map((days) => {
            const active = !fy && from === daysAgo(days - 1) && to === today();
            return (
              <button key={days} type="button" aria-pressed={active} className={cn("h-10 rounded-sm border px-3 text-sm font-medium", active ? "border-primary bg-primary text-primary-foreground" : "border-border bg-card hover:border-foreground")} onClick={() => { setFrom(daysAgo(days - 1)); setTo(today()); }}>{days === 365 ? "12 months" : `${days} days`}</button>
            );
          })}
        </div>
      </div>
      {fy ? <p className="-mt-2 text-xs text-muted-foreground">{fyLabel(fy)}: 1 April {fy} – 31 March {fy + 1}{fy === currentFyStart() ? ", so far" : ""}. Download the full report for the accountant.</p> : null}
      {report.isPending ? <LoadingState lines={8} /> : report.isError ? <ErrorState onRetry={() => void report.refetch()} /> : (
        <>
          <ul className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {[
              ["Orders", String(report.data.summary.orders)], ["Gross sales", formatMoney(report.data.summary.gross_sales)], ["Net sales", formatMoney(report.data.summary.net_sales)],
              ["Average order", formatMoney(report.data.summary.average_order_value)], ["Discounts", formatMoney(report.data.summary.discounts)], ["Delivery charged", formatMoney(report.data.summary.shipping)],
              ["GST collected (incl.)", formatMoney(report.data.summary.tax)], ["Refunds", formatMoney(report.data.summary.refunds)],
            ].map(([label, value]) => <li key={label} className="border border-border bg-card p-4"><p className="eyebrow text-muted-foreground">{label}</p><p className="mt-1 text-xl font-semibold">{value}</p></li>)}
          </ul>
          <TimeColumns
            title="Revenue per day"
            summary={`${formatMoney(report.data.summary.net_sales)} net sales from ${report.data.summary.orders} orders`}
            data={report.data.daily.map((d) => ({ bucket: d.date, revenue: d.revenue }))}
            valueKey="revenue" valueName="Revenue" format={formatInr} axisFormat={formatInrCompact} height={260}
          />
          <div className="grid gap-4 lg:grid-cols-2">
            <Panel title="By product">
              <table className="w-full text-sm"><thead className="text-left text-xs text-muted-foreground uppercase"><tr><th className="py-1">Product</th><th className="py-1">Units</th><th className="py-1 text-right">Revenue</th></tr></thead>
                <tbody className="divide-y divide-border">{report.data.by_product.map((p) => <tr key={p.name}><td className="py-2">{p.name} <span className="text-xs text-muted-foreground">{p.type}</span></td><td className="py-2">{p.units}</td><td className="py-2 text-right">{formatMoney(p.revenue)}</td></tr>)}</tbody>
              </table>
            </Panel>
            <Panel title="By category">
              <table className="w-full text-sm"><thead className="text-left text-xs text-muted-foreground uppercase"><tr><th className="py-1">Category</th><th className="py-1">Units</th><th className="py-1 text-right">Revenue</th></tr></thead>
                <tbody className="divide-y divide-border">{report.data.by_category.map((c) => <tr key={c.category}><td className="py-2">{c.category}</td><td className="py-2">{c.units}</td><td className="py-2 text-right">{formatMoney(c.revenue)}</td></tr>)}</tbody>
              </table>
            </Panel>
          </div>
          <InternationalSummary from={from} to={to} />
        </>
      )}
    </AdminPage>
  );
}

/**
 * Sales by currency for the same period (staff with financial reports only), with the way
 * to the order-by-order detail and its Excel file.
 */
function InternationalSummary({ from, to }: { from: string; to: string }) {
  const me = useQueryClient().getQueryData<AdminMe>(adminMeKey);
  const allowed = Boolean(me?.permissions.includes("reports.finance"));
  const fx = useQuery({ queryKey: ["admin", "fx-report", from, to], queryFn: () => miscAdminApi.fxReport(from, to), enabled: allowed, placeholderData: (p) => p });
  if (!allowed) return null;
  if (fx.isPending) return <Panel title="Sales by currency"><LoadingState lines={3} /></Panel>;
  if (fx.isError) {
    const tooLong = fx.error instanceof ApiError && fx.error.isValidation;
    return <Panel title="Sales by currency">{tooLong ? <p className="text-sm text-muted-foreground">Choose a period of up to two years to see sales by currency.</p> : <ErrorState onRetry={() => void fx.refetch()} />}</Panel>;
  }
  const abroad = fx.data.by_currency.filter((c) => c.currency !== fx.data.store_currency);

  return (
    <ByCurrency
      report={fx.data}
      title={abroad.length ? `Sales by currency · ${abroad.reduce((n, c) => n + c.orders, 0)} international orders` : "Sales by currency · no international orders in this period"}
      actions={
        <div className="flex flex-wrap items-center gap-2">
          <ExportButton type="international" from={from} to={to} label="International (Excel)" />
          <Link href="/admin/international" className="inline-flex min-h-10 items-center text-sm font-medium underline underline-offset-4">Order-by-order details</Link>
        </div>
      }
    />
  );
}
