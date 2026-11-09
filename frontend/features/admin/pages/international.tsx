"use client";

import { useQuery } from "@tanstack/react-query";
import { Info } from "lucide-react";
import Link from "next/link";
import { useState, type ReactNode } from "react";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { formatMoney } from "@/lib/money";
import { countryName } from "@/lib/regions";
import { miscAdminApi, type FxReport } from "../api";
import { ExportButton } from "../components/export-button";
import { AdminPage, Field, inputClass, Panel } from "../components/kit/admin-page";
import { currentFyStart, financialYears, fyLabel, fyRange, monthRange, thisMonth, today } from "../lib/periods";

const monthLabel = (ym: string) => new Intl.DateTimeFormat("en-IN", { month: "short", year: "numeric", timeZone: "UTC" }).format(new Date(`${ym}-01T00:00:00Z`));
type Mode = "month" | "year" | "custom";
const when = (iso: string | null) => (iso ? new Intl.DateTimeFormat("en-IN", { dateStyle: "medium", timeStyle: "short", timeZone: "Asia/Kolkata" }).format(new Date(iso)) : "—");
/** "0.010633" per ₹1 → "₹94.05" per unit (display only). */
const perUnit = (rate: string, store: string) => {
  const value = Number(rate);
  return value > 0 ? new Intl.NumberFormat("en-IN", { style: "currency", currency: store }).format(1 / value) : "—";
};
const th = "px-3 py-2 font-semibold";
const td = "px-3 py-2 whitespace-nowrap";

/**
 * Admin → International sales: the month-end FX audit (ARCHITECTURE D21). Each order abroad
 * is shown at the rate it was locked to, so a morning and an evening sale at different
 * rates each reconcile on their own.
 */
export function InternationalSalesPage() {
  const [mode, setMode] = useState<Mode>("month");
  const [month, setMonth] = useState(thisMonth);
  const [fy, setFy] = useState(currentFyStart);
  const [custom, setCustom] = useState<[string, string]>(() => [fyRange(currentFyStart())[0], today()]);
  const [from, to] = mode === "month" ? monthRange(month) : mode === "year" ? fyRange(fy) : custom;
  const report = useQuery({ queryKey: ["admin", "fx-report", from, to], queryFn: () => miscAdminApi.fxReport(from, to), placeholderData: (p) => p });
  // Financial years from when international selling was built (FY 2026–27) to the current one.
  const years = financialYears(2026);

  return (
    <AdminPage
      title="International sales"
      description="What customers abroad paid, the exchange rate each order was locked to, and the rupee value in your books."
      actions={<ExportButton type="international" from={from} to={to} label="Download period (Excel)" />}
    >
      <div className="flex flex-wrap items-end gap-3">
        <div role="group" aria-label="Period" className="flex h-10 overflow-hidden rounded-sm border border-border bg-card text-sm">
          {([["month", "Month"], ["year", "Financial year"], ["custom", "Custom"]] as const).map(([value, label]) => (
            <button key={value} type="button" aria-pressed={mode === value} onClick={() => setMode(value)} className={mode === value ? "bg-primary px-3 font-medium text-primary-foreground" : "px-3 hover:bg-muted"}>{label}</button>
          ))}
        </div>
        {mode === "month" ? (
          <Field label="Month" htmlFor="fx-month" className="w-48">
            <input id="fx-month" type="month" className={inputClass} value={month} max={thisMonth()} onChange={(e) => e.target.value && setMonth(e.target.value)} />
          </Field>
        ) : mode === "year" ? (
          <Field label="Financial year (April–March)" htmlFor="fx-year" className="w-56">
            <select id="fx-year" className={inputClass} value={fy} onChange={(e) => setFy(Number(e.target.value))}>
              {years.map((y) => <option key={y} value={y}>{fyLabel(y)}</option>)}
            </select>
          </Field>
        ) : (
          <>
            <Field label="From" htmlFor="fx-from" className="w-44"><input id="fx-from" type="date" className={inputClass} value={custom[0]} max={custom[1]} onChange={(e) => e.target.value && setCustom([e.target.value, custom[1]])} /></Field>
            <Field label="To" htmlFor="fx-to" className="w-44"><input id="fx-to" type="date" className={inputClass} value={custom[1]} min={custom[0]} max={today()} onChange={(e) => e.target.value && setCustom([custom[0], e.target.value])} /></Field>
          </>
        )}
      </div>
      {mode === "custom" ? <p className="-mt-2 text-xs text-muted-foreground">Up to two years at a time.</p> : null}

      <HowToAudit />

      {report.isPending ? <LoadingState lines={8} /> : report.isError ? <ErrorState onRetry={() => void report.refetch()} /> : (
        <>
          <ByCurrency report={report.data} />
          <ByMonth report={report.data} />
          <Orders report={report.data} />
          <Rates report={report.data} from={from} to={to} />
        </>
      )}
    </AdminPage>
  );
}

function HowToAudit() {
  return (
    <details className="group border border-border bg-card p-4 text-sm">
      <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 font-medium">
        <Info className="size-4 text-primary" aria-hidden /> How the month-end audit works
      </summary>
      <ol className="mt-3 grid list-decimal gap-2 pl-5 text-muted-foreground">
        <li>For a yearly audit choose <strong className="text-foreground">Financial year</strong> (April–March) and download the Excel file: it adds a month-by-month sheet and every rate fetched in the year.</li>
        <li>Rates are fetched twice a day and <strong className="text-foreground">every fetch is kept</strong> (Rate history below).</li>
        <li>When a customer places an order, the rate shown to them — market rate plus your markup — is <strong className="text-foreground">locked on that order</strong>. A morning order and an evening order can have different rates; each is correct for its own time.</li>
        <li>Every order is booked in rupees at its own total, so <strong className="text-foreground">“Booked in rupees” is the figure for your accounts and GST</strong>. Add the column; never convert the month&apos;s foreign total at one rate.</li>
        <li>Razorpay converts what it collects into rupees at its own rate when it settles. Match each <strong className="text-foreground">payment reference</strong> with the Razorpay settlement report; the small difference is your bank&apos;s exchange gain or loss.</li>
      </ol>
    </details>
  );
}

/** Also shown on the Reports page, so both screens always agree. */
export function ByCurrency({ report, title = "Totals by currency", actions }: { report: FxReport; title?: string; actions?: ReactNode }) {
  if (!report.by_currency.length) {
    return <EmptyState title="No sales in this period" description="Paid orders and refunds in every currency will appear here." />;
  }
  return (
    <Panel title={title} actions={actions}>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
            <tr><th className={th}>Currency</th><th className={th}>Paid orders</th><th className={`${th} text-right`}>Customers paid</th><th className={`${th} text-right`}>Booked in rupees</th><th className={th}>Refunds</th><th className={`${th} text-right`}>Net (their currency)</th><th className={`${th} text-right`}>Net in rupees</th><th className={`${th} text-right`}>Average rate</th></tr>
          </thead>
          <tbody className="divide-y divide-border">
            {report.by_currency.map((c) => (
              <tr key={c.currency}>
                <td className={`${td} font-medium`}>{c.currency}</td>
                <td className={td}>{c.orders}</td>
                <td className={`${td} text-right`}>{formatMoney(c.charged)}</td>
                <td className={`${td} text-right`}>{formatMoney(c.booked)}</td>
                <td className={td}>{c.refunds ? `${c.refunds} · −${formatMoney(c.refunded_charged)}` : "—"}</td>
                <td className={`${td} text-right`}>{formatMoney(c.net_charged)}</td>
                <td className={`${td} text-right font-semibold`}>{formatMoney(c.net_booked)}</td>
                <td className={`${td} text-right text-muted-foreground`}>{c.average_rate ? `1 ${c.currency} = ${perUnit(c.average_rate, report.store_currency)}` : "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="text-xs text-muted-foreground">Sales count on the day they were paid, refunds on the day they were made (India time). Average rate = total paid ÷ rupees booked.</p>
    </Panel>
  );
}

/** For periods longer than a month: each month's figures and market-rate range per currency. */
function ByMonth({ report }: { report: FxReport }) {
  if (new Set(report.by_month.map((m) => m.month)).size < 2) return null;
  return (
    <Panel title="Month by month">
      <div className="max-h-120 overflow-auto">
        <table className="w-full text-sm">
          <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground uppercase">
            <tr><th className={th}>Month</th><th className={th}>Currency</th><th className={th}>Orders</th><th className={`${th} text-right`}>Customers paid</th><th className={`${th} text-right`}>Booked in rupees</th><th className={th}>Refunds</th><th className={`${th} text-right`}>Net in rupees</th><th className={th}>Rate range that month</th></tr>
          </thead>
          <tbody className="divide-y divide-border">
            {report.by_month.map((m) => (
              <tr key={`${m.month}-${m.currency}`}>
                <td className={`${td} font-medium`}>{monthLabel(m.month)}</td>
                <td className={td}>{m.currency}</td>
                <td className={td}>{m.orders}</td>
                <td className={`${td} text-right`}>{formatMoney(m.charged)}</td>
                <td className={`${td} text-right`}>{formatMoney(m.booked)}</td>
                <td className={td}>{m.refunds || "—"}</td>
                <td className={`${td} text-right font-semibold`}>{formatMoney(m.net_booked)}</td>
                <td className={`${td} text-muted-foreground`}>{m.rate_low && m.rate_high ? `1 ${m.currency} = ${perUnit(m.rate_high, report.store_currency)} – ${perUnit(m.rate_low, report.store_currency)}` : "—"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <p className="text-xs text-muted-foreground">Rupee figures can be added across months and currencies; foreign amounts are per currency.</p>
    </Panel>
  );
}

function Orders({ report }: { report: FxReport }) {
  return (
    <Panel title={`Orders paid in other currencies (${report.orders.length})`}>
      {report.orders.length === 0 ? <p className="text-sm text-muted-foreground">No orders were paid in another currency in this period.</p> : (
        <div className="max-h-120 overflow-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground uppercase">
              <tr><th className={th}>Order</th><th className={th}>Paid</th><th className={th}>Country</th><th className={`${th} text-right`}>Customer paid</th><th className={th}>Rate locked</th><th className={th}>Market rate · markup</th><th className={`${th} text-right`}>Booked in rupees</th><th className={th}>Payment reference</th></tr>
            </thead>
            <tbody className="divide-y divide-border">
              {report.orders.map((o) => (
                <tr key={o.order_number}>
                  <td className={td}><Link href={`/admin/orders/${o.order_number}`} className="font-medium underline-offset-4 hover:underline">{o.order_number}</Link></td>
                  <td className={td}>{when(o.paid_at)}</td>
                  <td className={td}>{o.country ? countryName(o.country) : "—"}</td>
                  <td className={`${td} text-right font-medium`}>{formatMoney(o.charged)}</td>
                  <td className={td}>1 {o.currency} = {perUnit(o.applied_rate, report.store_currency)}<span className="block text-xs text-muted-foreground">rate from {when(o.rate_fetched_at)}</span></td>
                  <td className={`${td} text-muted-foreground`}>{o.market_rate ? `1 ${o.currency} = ${perUnit(o.market_rate, report.store_currency)}` : "—"}{o.markup_bps ? ` · +${o.markup_bps / 100}%` : ""}</td>
                  <td className={`${td} text-right`}>{formatMoney(o.booked)}{o.refunded_booked.amount ? <span className="block text-xs text-muted-foreground">refunded {formatMoney(o.refunded_booked)}</span> : null}</td>
                  <td className={`${td} font-mono text-xs`}>{o.payment_reference ?? "—"}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Panel>
  );
}

function Rates({ report, from, to }: { report: FxReport; from: string; to: string }) {
  const [currency, setCurrency] = useState<string | null>(null);
  const selected = currency ?? report.rates[0]?.currency ?? null;
  const history = useQuery({ queryKey: ["admin", "fx-history", selected, from, to], queryFn: () => miscAdminApi.rateHistory(selected!, from, to), enabled: selected !== null });

  return (
    <Panel title="Rate history">
      {report.rates.length === 0 ? <p className="text-sm text-muted-foreground">No exchange rates were fetched in this period.</p> : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/40 text-left text-xs text-muted-foreground uppercase">
                <tr><th className={th}>Currency</th><th className={th}>Fetches</th><th className={th}>Start of period</th><th className={th}>Lowest</th><th className={th}>Highest</th><th className={th}>Latest</th><th className={th}><span className="sr-only">Details</span></th></tr>
              </thead>
              <tbody className="divide-y divide-border">
                {report.rates.map((r) => (
                  <tr key={r.currency} className={r.currency === selected ? "bg-muted/30" : undefined}>
                    <td className={`${td} font-medium`}>{r.currency}</td>
                    <td className={td}>{r.fetches}</td>
                    {[r.first, r.high, r.low, r.last].map((rate, i) => <td key={i} className={td}>{perUnit(rate, report.store_currency)}</td>)}
                    <td className={td}><button type="button" onClick={() => setCurrency(r.currency)} aria-pressed={r.currency === selected} className="inline-flex min-h-11 items-center text-xs font-medium underline underline-offset-4">Every fetch</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="text-xs text-muted-foreground">Rupees per 1 unit of each currency, at the market rate (before your markup). A stronger rupee means a smaller number.</p>
          {selected ? (
            <div className="grid gap-2">
              <h3 className="text-sm font-semibold">Every {selected} rate fetched</h3>
              {history.isPending ? <LoadingState lines={3} /> : history.isError ? <ErrorState onRetry={() => void history.refetch()} /> : (
                <ul className="grid max-h-80 divide-y divide-border overflow-y-auto border border-border text-sm">
                  {history.data.items.map((h) => (
                    <li key={h.fetched_at} className="flex justify-between gap-3 px-3 py-2">
                      <span>{when(h.fetched_at)}</span>
                      <span className="font-medium">1 {selected} = {perUnit(h.rate, report.store_currency)}</span>
                      <span className="hidden text-muted-foreground sm:inline">₹1 = {h.rate} {selected}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ) : null}
        </>
      )}
    </Panel>
  );
}
