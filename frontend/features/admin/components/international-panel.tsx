"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CircleAlert, CircleCheck, Globe, RefreshCw } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { countryName } from "@/lib/regions";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type InternationalSettings } from "../api";
import { checkboxLabelClass, Field, FormActions, inputClass, Panel, textareaClass } from "./kit/admin-page";

const key = ["admin", "settings", "international"] as const;
const currencyNames = typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "currency" }) : null;
const ago = (iso: string) => new Intl.RelativeTimeFormat("en", { numeric: "auto" }).format(Math.round((new Date(iso).getTime() - Date.now()) / 3_600_000), "hour");

/** Admin → Settings → International: sell abroad in shoppers' own currencies (ARCHITECTURE D21). */
export function InternationalPanel() {
  const query = useQuery({ queryKey: key, queryFn: miscAdminApi.international });
  if (query.isPending) return <Panel title="International selling"><LoadingState lines={5} /></Panel>;
  if (query.isError) return <Panel title="International selling"><ErrorState onRetry={() => void query.refetch()} /></Panel>;
  return <InternationalForm key={JSON.stringify(query.data.settings)} data={query.data} />;
}

function InternationalForm({ data }: { data: InternationalSettings }) {
  const queryClient = useQueryClient();
  const [form, setForm] = useState({ ...data.settings, markup: String(data.settings.markup_bps / 100) });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const err = (k: string) => errors[k]?.[0];
  const store = (fresh: InternationalSettings) => queryClient.setQueryData(key, fresh);
  const onError = (e: unknown) => {
    if (e instanceof ApiError) setErrors(e.fieldErrors);
    toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Could not save.");
  };

  const save = useMutation({
    mutationFn: () => miscAdminApi.saveInternational({
      enabled: form.enabled, currencies: form.currencies, fallback_currency: form.fallback_currency,
      markup_bps: Math.round(Number(form.markup || 0) * 100), unavailable_message: form.unavailable_message,
    }),
    onSuccess: (fresh) => { setErrors({}); store(fresh); toast.success("International selling settings saved."); },
    onError,
  });
  const refresh = useMutation({
    mutationFn: miscAdminApi.refreshRates,
    onSuccess: (fresh) => { store(fresh); toast.success("Exchange rates updated."); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not fetch exchange rates."),
  });

  const installGeo = useMutation({
    mutationFn: miscAdminApi.installGeoIp,
    onSuccess: (fresh) => { store(fresh); toast.success("Visitor location lookup installed. Shoppers now get their currency automatically."); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not install the location database.", { duration: 15_000 }),
  });

  const toggleCurrency = (code: string, on: boolean) => setForm((f) => {
    const currencies = on ? [...f.currencies, code] : f.currencies.filter((c) => c !== code);
    return { ...f, currencies, fallback_currency: currencies.includes(f.fallback_currency) ? f.fallback_currency : (currencies[0] ?? f.fallback_currency) };
  });
  const base = data.store_currency;

  return (
    <Panel title="International selling">
      <div className="flex items-start justify-between gap-4">
        <div className="grid gap-1">
          <p id="intl-label" className="text-sm font-medium">Sell and deliver outside India</p>
          <p id="intl-desc" className="text-xs text-muted-foreground">
            When on, shoppers abroad see prices in their own currency (picked from where they are, changeable in the footer) and pay in it.
            When off, everything stays in {base}, only Indian addresses are accepted, and visitors abroad see your message below.
          </p>
        </div>
        <button
          type="button" role="switch" aria-checked={form.enabled} aria-labelledby="intl-label" aria-describedby="intl-desc"
          onClick={() => setForm((f) => ({ ...f, enabled: !f.enabled }))}
          className={cn("relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none", form.enabled ? "bg-primary" : "bg-stone-300")}
        >
          <span className={cn("inline-block size-6 rounded-full bg-white shadow transition-transform duration-300 ease-out", form.enabled ? "translate-x-5" : "translate-x-0")} />
        </button>
      </div>

      <fieldset className="grid gap-2">
        <legend className="mb-1 text-sm font-medium">Currencies shoppers can pay in</legend>
        <p className="text-xs text-muted-foreground">{base} is always available. Rates are today&apos;s market rate plus your markup.</p>
        {err("currencies") ? <p className="text-sm text-destructive">{err("currencies")}</p> : null}
        <div className="grid gap-x-4 sm:grid-cols-2 xl:grid-cols-3">
          {data.currencies.map((c) => (
            <label key={c.code} className={checkboxLabelClass}>
              <input type="checkbox" className="size-4 accent-primary" checked={form.currencies.includes(c.code)} onChange={(e) => toggleCurrency(c.code, e.target.checked)} />
              <span className="min-w-0">
                <span className="font-medium">{c.code}</span> <span className="text-muted-foreground">{currencyNames?.of(c.code)}</span>
                <span className="block text-xs text-muted-foreground">{c.rate ? `1 ${base} = ${c.rate}` : "No rate yet"}</span>
              </span>
            </label>
          ))}
        </div>
      </fieldset>

      <div className="grid gap-4 md:grid-cols-2">
        <Field label="When a shopper's own currency is not offered" htmlFor="intl-fallback" required error={err("fallback_currency")} hint="For example, Japanese visitors see this currency if you don't offer JPY.">
          <select id="intl-fallback" className={inputClass} value={form.fallback_currency} onChange={(e) => setForm({ ...form, fallback_currency: e.target.value })}>
            {(form.currencies.length ? form.currencies : [form.fallback_currency]).map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </Field>
        <Field label="Markup on the exchange rate (%)" htmlFor="intl-markup" required error={err("markup_bps")} hint="0–10%. Covers card conversion and international transaction fees.">
          <input id="intl-markup" inputMode="decimal" className={inputClass} value={form.markup} onChange={(e) => setForm({ ...form, markup: e.target.value })} />
        </Field>
        <Field label="Message for visitors you can't deliver to yet" htmlFor="intl-message" required error={err("unavailable_message")} className="md:col-span-2" hint="Shown once as a popup and again in the bag and at checkout while international selling is off.">
          <textarea id="intl-message" maxLength={240} className={textareaClass} value={form.unavailable_message} onChange={(e) => setForm({ ...form, unavailable_message: e.target.value })} />
        </Field>
      </div>

      <ul className="grid gap-2 text-sm">
        <li className="flex items-start gap-2">
          {data.rates_stale ? <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-700" aria-hidden /> : <CircleCheck className="mt-0.5 size-4 shrink-0 text-emerald-700" aria-hidden />}
          <span>{data.rates_updated_at ? <>Exchange rates updated {ago(data.rates_updated_at)}{data.rates_stale ? " — older than two days; check the server's scheduled tasks." : ". They refresh automatically twice a day."}</> : "Exchange rates have not been fetched yet."}</span>
        </li>
        <li className="flex items-start gap-2">
          <Globe className="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden />
          <span>
            {data.geoip.your_country ? <>The store sees you in <strong>{countryName(data.geoip.your_country)}</strong>. If that&apos;s wrong, visitors may get the wrong currency.</>
              : data.geoip.ready ? "The store could not tell where you are (a private network or a proxy hides your address)."
              : <>The visitor location database is not installed yet, so for now the currency is guessed from each shopper&apos;s browser language.</>}
          </span>
        </li>
        {!data.geoip.ready ? (
          <li className="pl-6">
            <Button type="button" size="sm" variant="outline" onClick={() => installGeo.mutate()} disabled={installGeo.isPending}>
              <Globe className="size-4" aria-hidden /> {installGeo.isPending ? "Installing… (up to a minute)" : "Install location lookup now"}
            </Button>
          </li>
        ) : null}
      </ul>
      <p className="border-l-4 border-amber-500 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-950">
        Before switching on: ask Razorpay to activate <strong>International payments</strong> for your account, or foreign cards will be declined. International orders are not booked with Shiprocket; ship them with your international courier and add the tracking number on the order. GST invoices stay in rupees; check export rules with your accountant.
      </p>

      <FormActions>
        <Button type="button" onClick={() => save.mutate()} disabled={save.isPending}>{save.isPending ? "Saving…" : "Save international settings"}</Button>
        <Button type="button" variant="outline" onClick={() => refresh.mutate()} disabled={refresh.isPending}>
          <RefreshCw className={cn("size-4", refresh.isPending && "motion-safe:animate-spin")} aria-hidden /> Update rates now
        </Button>
      </FormActions>
    </Panel>
  );
}
