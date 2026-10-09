"use client";

import { useState, type ReactNode } from "react";
import { cn } from "@/lib/utils";

export function AdminPage({ title, description, actions, children }: { title: string; description?: string; actions?: ReactNode; children: ReactNode }) {
  return (
    <div className="grid gap-6">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">{title}</h1>
          {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
        </div>
        {actions ? <div className="flex flex-wrap gap-2">{actions}</div> : null}
      </header>
      {children}
    </div>
  );
}

export function Panel({ title, actions, children, className }: { title?: string; actions?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <section className={`grid content-start gap-4 border border-border bg-card p-5 ${className ?? ""}`}>
      {title || actions ? (
        <div className="flex flex-wrap items-center justify-between gap-3">
          {title ? <h2 className="text-base font-semibold">{title}</h2> : <span />}
          {actions}
        </div>
      ) : null}
      {children}
    </section>
  );
}

export function StatusBadge({ value }: { value: string }) {
  // Pop once when the value changes (e.g. Processing → Packed), not on every first render.
  const [seen, setSeen] = useState(value);
  const [changes, setChanges] = useState(0);
  if (seen !== value) {
    setSeen(value);
    setChanges((n) => n + 1);
  }
  const tone: Record<string, string> = {
    active: "bg-emerald-100 text-emerald-900", published: "bg-emerald-100 text-emerald-900", delivered: "bg-emerald-100 text-emerald-900",
    captured: "bg-emerald-100 text-emerald-900", available: "bg-emerald-100 text-emerald-900", processing: "bg-amber-100 text-amber-900",
    paid: "bg-amber-100 text-amber-900", shipped: "bg-sky-100 text-sky-900", out_for_delivery: "bg-sky-100 text-sky-900", packed: "bg-sky-100 text-sky-900",
    pending: "bg-muted text-foreground", draft: "bg-muted text-foreground", initiated: "bg-muted text-foreground", downloaded: "bg-sky-100 text-sky-900",
    cancelled: "bg-stone-200 text-stone-800", archived: "bg-stone-200 text-stone-800", failed: "bg-red-100 text-red-900", revoked: "bg-red-100 text-red-900", deactivated: "bg-red-100 text-red-900", blocked: "bg-red-100 text-red-900",
    refunded: "bg-red-100 text-red-900", partially_refunded: "bg-red-100 text-red-900", refund_requested: "bg-red-100 text-red-900",
  };
  return <span key={changes} className={cn("inline-flex items-center rounded-sm px-2 py-0.5 text-xs font-medium whitespace-nowrap capitalize transition-colors duration-300", tone[value] ?? "bg-muted text-foreground", changes > 0 && "animate-pop")}>{value.replaceAll("_", " ")}</span>;
}

export function Pagination({ page, lastPage, onChange }: { page: number; lastPage: number; onChange: (page: number) => void }) {
  if (lastPage <= 1) return null;
  const button = "inline-flex min-h-10 items-center border border-border px-3 text-sm hover:border-foreground hover:bg-card disabled:opacity-40";
  return (
    <nav aria-label="Pagination" className="flex items-center justify-end gap-2">
      <button type="button" className={button} disabled={page <= 1} onClick={() => onChange(page - 1)}>Previous</button>
      <span className="text-sm text-muted-foreground">Page {page} of {lastPage}</span>
      <button type="button" className={button} disabled={page >= lastPage} onClick={() => onChange(page + 1)}>Next</button>
    </nav>
  );
}

/**
 * Label + control + hint/error. `required` shows a red asterisk (announced as "required").
 * Labels share one line height so controls in the same row align.
 */
export function Field({ label, htmlFor, required, error, hint, className, children }: { label: string; htmlFor: string; required?: boolean; error?: string; hint?: string; className?: string; children: ReactNode }) {
  return (
    <div className={cn("grid content-start gap-1.5", className)}>
      <label htmlFor={htmlFor} className="text-sm leading-5 font-medium text-foreground">
        {label}
        {required ? (
          <>
            <span className="ml-0.5 text-red-600" aria-hidden="true">*</span>
            <span className="sr-only"> (required)</span>
          </>
        ) : null}
      </label>
      {children}
      {error ? <p className="text-sm text-destructive">{error}</p> : hint ? <p className="text-xs leading-4 text-muted-foreground">{hint}</p> : null}
    </div>
  );
}

/** One row of filters above a list; controls keep a fixed width instead of stretching. */
export function FilterBar({ children }: { children: ReactNode }) {
  return <div className="flex flex-wrap items-center gap-3 [&>*]:w-full sm:[&>*]:w-56">{children}</div>;
}

/** Footer row for form actions, aligned to the start. */
export function FormActions({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={cn("flex flex-wrap items-center gap-2 pt-1", className)}>{children}</div>;
}

export function RequiredNote() {
  return <p className="text-xs text-muted-foreground"><span className="text-red-600" aria-hidden="true">*</span> Required field</p>;
}

const controlBase =
  "w-full rounded-sm border border-input bg-white px-3 text-sm text-foreground outline-none transition-colors placeholder:text-muted-foreground/80 focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/25 disabled:cursor-not-allowed disabled:opacity-60 aria-invalid:border-destructive";

/** Inputs and selects: identical 40px height so they line up in any row. */
export const inputClass = `${controlBase} h-10`;
/** Multi-line text. */
export const textareaClass = `${controlBase} min-h-24 py-2 leading-relaxed`;
export const checkboxLabelClass = "flex min-h-10 items-center gap-2.5 text-sm";
