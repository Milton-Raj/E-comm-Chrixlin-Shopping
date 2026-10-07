"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { formatMoney } from "@/lib/money";
import { miscAdminApi } from "../api";
import { formatInr, formatInrCompact, TimeColumns } from "../components/charts";
import { ExportButton } from "../components/export-button";
import { AdminPage, Field, inputClass, Panel } from "../components/kit/admin-page";

const iso = (d: Date) => d.toISOString().slice(0, 10);

export function ReportsPage() {
  const [from, setFrom] = useState(() => iso(new Date(Date.now() - 29 * 86_400_000)));
  const [to, setTo] = useState(() => iso(new Date()));
  const report = useQuery({ queryKey: ["admin", "sales", from, to], queryFn: () => miscAdminApi.sales(from, to), placeholderData: (p) => p });

  return (
    <AdminPage title="Reports" description="Sales, products and categories for any date range (paid orders only)." actions={<ExportButton type="report" from={from} to={to} label="Download full report (Excel)" />}>
      <div className="flex flex-wrap items-end gap-3 [&>div]:w-44">
        <Field label="From" htmlFor="r-from"><input id="r-from" type="date" className={inputClass} value={from} max={to} onChange={(e) => setFrom(e.target.value)} /></Field>
        <Field label="To" htmlFor="r-to"><input id="r-to" type="date" className={inputClass} value={to} min={from} onChange={(e) => setTo(e.target.value)} /></Field>
        {[7, 30, 90, 365].map((days) => (
          <button key={days} type="button" className="h-10 rounded-sm border border-border bg-card px-3 text-sm font-medium hover:border-foreground" onClick={() => { setFrom(iso(new Date(Date.now() - (days - 1) * 86_400_000))); setTo(iso(new Date())); }}>{days === 365 ? "12 months" : `${days} days`}</button>
        ))}
      </div>
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
        </>
      )}
    </AdminPage>
  );
}
