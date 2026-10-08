"use client";

import type { ReactNode } from "react";
import { Bar, BarChart, CartesianGrid, LabelList, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from "recharts";

/**
 * Admin charts. Palette validated with the dataviz validator (light surface):
 * series-1 #9b2d3b (brand burgundy step) + series-2 #2a78d6 pass lightness, chroma,
 * CVD (ΔE 23.6) and contrast. Single-series charts use series-1 only.
 * Text never wears the series colour; values are printed so charts read without hovering.
 */
export const SERIES = { one: "#9b2d3b", two: "#2a78d6" } as const;
const GRID = "#e4dfd8";
const AXIS_TEXT = "#5e503f";
const INK = "#0a0908";

const inr = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", notation: "compact", maximumFractionDigits: 1 });
const inrFull = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 });
const num = new Intl.NumberFormat("en-IN");

/** Minor units (paise) → compact rupees, e.g. 3524980 → ₹35.2K. */
export const formatInrCompact = (paise: number) => inr.format(paise / 100).replace(".0", "");
export const formatInr = (paise: number) => inrFull.format(paise / 100);
export const formatCount = (n: number) => num.format(n);

export function bucketLabel(bucket: string, long = false): string {
  if (/^\d{4}-\d{2}$/.test(bucket)) {
    const [y, m] = bucket.split("-").map(Number);
    return new Date(y!, m! - 1, 1).toLocaleDateString("en-IN", { month: "short", year: long ? "numeric" : "2-digit" });
  }
  return new Date(`${bucket}T00:00:00`).toLocaleDateString("en-IN", long ? { day: "numeric", month: "short", year: "numeric" } : { day: "numeric", month: "short" });
}

type Point = { bucket: string } & Record<string, number | string>;

/**
 * Which values get printed on the chart: every non-zero point when there are few,
 * otherwise the three highest and the most recent. Selected by value, because the
 * chart library skips zero-height marks when numbering labels.
 */
function labelledValues(values: number[]): (value: number, isLast: boolean) => boolean {
  const nonZero = values.filter((v) => v > 0);
  if (nonZero.length <= 16) return (v) => v > 0;
  const top = new Set([...nonZero].sort((a, b) => b - a).slice(0, 3));
  const lastValue = values[values.length - 1] ?? 0;
  return (v, isLast) => v > 0 && (top.has(v) || (isLast && v === lastValue));
}

function ChartFrame({ title, summary, children, table }: { title: string; summary?: ReactNode; children: ReactNode; table?: ReactNode }) {
  return (
    <figure className="grid content-start gap-3 border border-border bg-card p-5">
      <figcaption className="grid gap-1">
        <span className="text-base font-semibold text-foreground">{title}</span>
        {summary ? <span className="text-sm text-muted-foreground">{summary}</span> : null}
      </figcaption>
      {children}
      {table ? (
        <details className="text-sm">
          <summary className="inline-flex min-h-9 cursor-pointer items-center text-muted-foreground underline-offset-4 hover:underline">View data table</summary>
          <div className="mt-2 max-h-64 overflow-auto">{table}</div>
        </details>
      ) : null}
    </figure>
  );
}

function tooltipBox({ active, payload, label, format, name, long }: { active?: boolean; payload?: { value?: number }[]; label?: string; format: (v: number) => string; name: string; long: boolean }) {
  if (!active || !payload?.length) return null;
  return (
    <div className="rounded-sm bg-brand-black px-3 py-2 text-xs text-brand-cultured shadow-lg">
      <p className="font-semibold">{label ? bucketLabel(label, long) : ""}</p>
      <p>{name}: {format(Number(payload[0]?.value ?? 0))}</p>
    </div>
  );
}

function SeriesTable({ data, valueKey, format, valueName }: { data: Point[]; valueKey: string; format: (v: number) => string; valueName: string }) {
  return (
    <table className="w-full text-left text-sm tabular-nums">
      <thead className="text-xs text-muted-foreground uppercase"><tr><th className="py-1">Date</th><th className="py-1 text-right">{valueName}</th></tr></thead>
      <tbody className="divide-y divide-border">{data.map((d) => <tr key={d.bucket}><td className="py-1">{bucketLabel(d.bucket, true)}</td><td className="py-1 text-right">{format(Number(d[valueKey]))}</td></tr>)}</tbody>
    </table>
  );
}

/** Column chart over time (one measure, one colour), with axes and printed values. */
export function TimeColumns({ title, summary, data, valueKey, valueName, format, axisFormat, height = 260 }: {
  title: string; summary?: ReactNode; data: Point[]; valueKey: string; valueName: string; format: (v: number) => string; axisFormat: (v: number) => string; height?: number;
}) {
  const values = data.map((d) => Number(d[valueKey]));
  const showLabel = labelledValues(values);
  const monthly = data.length > 0 && /^\d{4}-\d{2}$/.test(data[0]!.bucket);

  return (
    <ChartFrame title={title} summary={summary} table={<SeriesTable data={data} valueKey={valueKey} format={format} valueName={valueName} />}>
      <div style={{ height }} role="img" aria-label={`${title}. ${typeof summary === "string" ? summary : ""}`}>
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 24, right: 8, bottom: 4, left: 4 }} barCategoryGap="18%">
            <CartesianGrid vertical={false} stroke={GRID} strokeWidth={1} />
            <XAxis dataKey="bucket" tickFormatter={(b: string) => bucketLabel(b)} tick={{ fill: AXIS_TEXT, fontSize: 12 }} tickLine={false} axisLine={{ stroke: GRID }} interval="preserveStartEnd" minTickGap={18} />
            <YAxis tickFormatter={axisFormat} tick={{ fill: AXIS_TEXT, fontSize: 12 }} tickLine={false} axisLine={false} width={64} allowDecimals={false} />
            <Tooltip cursor={{ fill: "rgba(10,9,8,0.04)" }} content={(p) => tooltipBox({ ...(p as object), format, name: valueName, long: !monthly } as Parameters<typeof tooltipBox>[0])} />
            <Bar dataKey={valueKey} fill={SERIES.one} radius={[4, 4, 0, 0]} maxBarSize={24} animationDuration={900} animationEasing="ease-out">
              <LabelList dataKey={valueKey} position="top" content={(props) => {
                const { x, y, width, value } = props as { x: number; y: number; width: number; value: number };
                if (!showLabel(Number(value), false)) return null;
                return <text x={x + width / 2} y={y - 6} textAnchor="middle" fill={INK} fontSize={11} fontWeight={600}>{axisFormat(Number(value))}</text>;
              }} />
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
    </ChartFrame>
  );
}

/** Line over time (one measure), dots on every point, values printed at the peak and the end. */
export function TimeLine({ title, summary, data, valueKey, valueName, format, height = 240 }: {
  title: string; summary?: ReactNode; data: Point[]; valueKey: string; valueName: string; format: (v: number) => string; height?: number;
}) {
  const values = data.map((d) => Number(d[valueKey]));
  const showLabel = labelledValues(values);
  const monthly = data.length > 0 && /^\d{4}-\d{2}$/.test(data[0]!.bucket);

  return (
    <ChartFrame title={title} summary={summary} table={<SeriesTable data={data} valueKey={valueKey} format={format} valueName={valueName} />}>
      <div style={{ height }} role="img" aria-label={`${title}. ${typeof summary === "string" ? summary : ""}`}>
        <ResponsiveContainer width="100%" height="100%">
          <LineChart data={data} margin={{ top: 24, right: 16, bottom: 4, left: 4 }}>
            <CartesianGrid vertical={false} stroke={GRID} strokeWidth={1} />
            <XAxis dataKey="bucket" tickFormatter={(b: string) => bucketLabel(b)} tick={{ fill: AXIS_TEXT, fontSize: 12 }} tickLine={false} axisLine={{ stroke: GRID }} interval="preserveStartEnd" minTickGap={18} />
            <YAxis tick={{ fill: AXIS_TEXT, fontSize: 12 }} tickLine={false} axisLine={false} width={40} allowDecimals={false} />
            <Tooltip content={(p) => tooltipBox({ ...(p as object), format, name: valueName, long: !monthly } as Parameters<typeof tooltipBox>[0])} />
            <Line type="monotone" dataKey={valueKey} stroke={SERIES.one} strokeWidth={2} strokeLinecap="round" dot={{ r: 4, fill: SERIES.one, stroke: "#f8f9f8", strokeWidth: 2 }} activeDot={{ r: 6 }} animationDuration={900} animationEasing="ease-out">
              <LabelList dataKey={valueKey} content={(props) => {
                const { x, y, value } = props as { x: number; y: number; value: number };
                if (!showLabel(Number(value), false)) return null;
                return <text x={x} y={y - 10} textAnchor="middle" fill={INK} fontSize={11} fontWeight={600}>{format(Number(value))}</text>;
              }} />
            </Line>
          </LineChart>
        </ResponsiveContainer>
      </div>
    </ChartFrame>
  );
}

/** Horizontal bars for ranked categories: name on the axis, value printed at every bar's tip. */
export function RankedBars({ title, summary, data, valueName, format, empty = "No data for this period yet." }: {
  title: string; summary?: ReactNode; data: { label: string; value: number; note?: string }[]; valueName: string; format: (v: number) => string; empty?: string;
}) {
  const height = Math.max(120, data.length * 40 + 16);
  return (
    <ChartFrame title={title} summary={summary} table={data.length ? (
      <table className="w-full text-left text-sm tabular-nums">
        <thead className="text-xs text-muted-foreground uppercase"><tr><th className="py-1">Name</th><th className="py-1 text-right">{valueName}</th></tr></thead>
        <tbody className="divide-y divide-border">{data.map((d) => <tr key={d.label}><td className="py-1">{d.label}</td><td className="py-1 text-right">{format(d.value)}{d.note ? ` · ${d.note}` : ""}</td></tr>)}</tbody>
      </table>
    ) : undefined}>
      {data.length ? (
        <div style={{ height }} role="img" aria-label={`${title}: ${data.map((d) => `${d.label} ${format(d.value)}`).join(", ")}`}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data.map((d) => ({ ...d, tip: d.note ? `${format(d.value)}\u0000${d.note}` : format(d.value) }))} layout="vertical" margin={{ top: 4, right: 190, bottom: 4, left: 4 }} barCategoryGap="28%">
              <CartesianGrid horizontal={false} stroke={GRID} strokeWidth={1} />
              <XAxis type="number" hide domain={[0, "dataMax"]} />
              <YAxis type="category" dataKey="label" width={170} tick={{ fill: INK, fontSize: 13 }} tickLine={false} axisLine={{ stroke: GRID }} interval={0} />
              <Tooltip cursor={{ fill: "rgba(10,9,8,0.04)" }} formatter={(v) => [format(Number(v)), valueName]} contentStyle={{ fontSize: 12 }} />
              <Bar dataKey="value" fill={SERIES.one} radius={[0, 4, 4, 0]} maxBarSize={22} animationDuration={900} animationEasing="ease-out">
                {/* Each row carries its own label text: label positions are not stable when the data changes. */}
                <LabelList dataKey="tip" position="right" content={(props) => {
                  const { x, y, width, height: h, value } = props as { x: number; y: number; width: number; height: number; value?: string };
                  if (typeof value !== "string") return null;
                  const [main, note] = value.split("\u0000");
                  return <text x={x + width + 8} y={y + h / 2} dominantBaseline="central" fill={INK} fontSize={12} fontWeight={600}>{main}{note ? <tspan fill={AXIS_TEXT} fontWeight={400}>{`  ${note}`}</tspan> : null}</text>;
                }} />
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>
      ) : <p className="py-8 text-center text-sm text-muted-foreground">{empty}</p>}
    </ChartFrame>
  );
}

/** Part-to-whole for two parts: one 100% bar, both segments labelled, plus a legend. */
export function SplitBar({ title, parts, format }: { title: string; parts: { label: string; value: number; sub?: string }[]; format: (v: number) => string }) {
  const total = parts.reduce((s, p) => s + p.value, 0);
  const colours = [SERIES.one, SERIES.two];
  return (
    <ChartFrame title={title} summary={`Total ${format(total)}`}>
      {total > 0 ? (
        <>
          <div className="animate-grow-x flex h-9 gap-0.5 overflow-hidden rounded-sm" role="img" aria-label={parts.map((p) => `${p.label} ${format(p.value)} (${Math.round((p.value / total) * 100)}%)`).join(", ")}>
            {parts.map((p, i) => p.value > 0 ? (
              <div key={p.label} className="flex items-center justify-center text-xs font-semibold text-white" style={{ width: `${(p.value / total) * 100}%`, background: colours[i] }}>
                {p.value / total >= 0.12 ? `${Math.round((p.value / total) * 100)}%` : ""}
              </div>
            ) : null)}
          </div>
          <ul className="grid gap-2 text-sm sm:grid-cols-2">
            {parts.map((p, i) => (
              <li key={p.label} className="flex items-start gap-2">
                <span className="mt-1 size-3 shrink-0 rounded-sm" style={{ background: colours[i] }} aria-hidden />
                <span><span className="font-medium">{p.label}</span> — {format(p.value)} ({total ? Math.round((p.value / total) * 100) : 0}%){p.sub ? <span className="block text-xs text-muted-foreground">{p.sub}</span> : null}</span>
              </li>
            ))}
          </ul>
        </>
      ) : <p className="py-6 text-center text-sm text-muted-foreground">No sales in this period yet.</p>}
    </ChartFrame>
  );
}
