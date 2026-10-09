"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CircleCheck, CircleAlert, Copy, Lock, ShieldCheck, ShieldOff } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { fromMinorUnits, toMinorUnits } from "@/lib/money-input";
import { indianStates } from "@/lib/regions";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type StoreSettings } from "../api";
import { AdminPage, checkboxLabelClass, Field, FormActions, inputClass, Panel, RequiredNote, textareaClass } from "../components/kit/admin-page";

const settingsKey = ["admin", "settings"] as const;

export function SettingsPage() {
  const settings = useQuery({ queryKey: settingsKey, queryFn: miscAdminApi.settings });
  if (settings.isPending) return <LoadingState lines={10} />;
  if (settings.isError) return <ErrorState onRetry={() => void settings.refetch()} />;
  return <SettingsForm key={JSON.stringify(settings.data)} settings={settings.data} />;
}

function SettingsForm({ settings }: { settings: StoreSettings }) {
  const queryClient = useQueryClient();
  const [store, setStore] = useState({
    name: settings.store.name, state_code: settings.store.state_code, support_email: settings.store.support_email ?? "",
    legal_name: settings.store.legal_name ?? "", gstin: settings.store.gstin ?? "", address: settings.store.address ?? "",
  });
  const [taxes, setTaxes] = useState(settings.tax_classes.map((t) => ({ ...t, rate: String(t.rate_bps / 100) })));
  const [shipping, setShipping] = useState(settings.shipping_methods.map((m) => ({ ...m, amountText: fromMinorUnits(m.amount), freeText: fromMinorUnits(m.free_over), minText: String(m.days_min), maxText: String(m.days_max) })));
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const update = (i: number, patch: Partial<(typeof shipping)[number]>) => setShipping(shipping.map((x, j) => (j === i ? { ...x, ...patch } : x)));

  const save = useMutation({
    mutationFn: () => miscAdminApi.saveSettings({
      store: {
        name: store.name, state_code: store.state_code, support_email: store.support_email || null,
        legal_name: store.legal_name.trim() || null, gstin: store.gstin.trim().toUpperCase() || null, address: store.address.trim() || null,
      },
      tax_classes: taxes.map((t) => ({ uuid: t.uuid, rate_bps: Math.round(Number(t.rate) * 100) })),
      shipping_methods: shipping.map((m) => ({
        uuid: m.uuid, amount: toMinorUnits(m.amountText) ?? 0, free_over: toMinorUnits(m.freeText), is_active: m.is_active,
        days_min: Number(m.minText), days_max: Number(m.maxText),
      })),
    }),
    onSuccess: () => { setErrors({}); toast.success("Settings saved."); void queryClient.invalidateQueries({ queryKey: settingsKey }); },
    onError: (e) => {
      if (e instanceof ApiError) setErrors(e.fieldErrors);
      toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Could not save.");
    },
  });
  const err = (k: string) => errors[k]?.[0];

  return (
    <AdminPage title="Settings" description="Store details, GST rates, delivery charges and security. Payment keys stay in the server environment." actions={<Button onClick={() => save.mutate()} disabled={save.isPending}>{save.isPending ? "Saving…" : "Save settings"}</Button>}>
      <RequiredNote />
      <div className="grid items-start gap-4 lg:grid-cols-2">
        <Panel title="Store">
          <Field label="Store name" htmlFor="s-name" required error={err("store.name")}><input id="s-name" className={inputClass} value={store.name} onChange={(e) => setStore({ ...store, name: e.target.value })} required /></Field>
          <Field label="Business state (for GST)" htmlFor="s-state" required hint="Same-state deliveries charge CGST + SGST; other states IGST.">
            <select id="s-state" className={inputClass} value={store.state_code} onChange={(e) => setStore({ ...store, state_code: e.target.value })}>{indianStates.map((s) => <option key={s.code} value={s.code}>{s.name}</option>)}</select>
          </Field>
          <Field label="Support email" htmlFor="s-email" error={err("store.support_email")}><input id="s-email" type="email" className={inputClass} value={store.support_email} onChange={(e) => setStore({ ...store, support_email: e.target.value })} /></Field>
          <p className="text-sm text-muted-foreground">Currency: {settings.store.currency} · all prices include GST.</p>
        </Panel>

        <Panel title="Tax invoice details">
          <p className="text-sm text-muted-foreground">Printed as the seller on every GST invoice. A PDF invoice is emailed to the customer automatically as soon as payment is confirmed.</p>
          <Field label="Registered business name" htmlFor="s-legal" hint="As on your GST registration. Leave empty to use the store name." error={err("store.legal_name")}>
            <input id="s-legal" className={inputClass} placeholder={store.name} value={store.legal_name} onChange={(e) => setStore({ ...store, legal_name: e.target.value })} />
          </Field>
          <Field label="GSTIN" htmlFor="s-gstin" hint="15 characters, starting with your state's GST code." error={err("store.gstin")}>
            <input id="s-gstin" className={cn(inputClass, "font-mono uppercase")} maxLength={15} placeholder="33ABCDE1234F1Z5" value={store.gstin} onChange={(e) => setStore({ ...store, gstin: e.target.value.toUpperCase() })} />
          </Field>
          <Field label="Registered address" htmlFor="s-address" hint="Printed on invoices exactly as typed." error={err("store.address")}>
            <textarea id="s-address" className={textareaClass} value={store.address} onChange={(e) => setStore({ ...store, address: e.target.value })} />
          </Field>
        </Panel>

        <Panel title="GST rates">
          <div className="grid gap-3 sm:grid-cols-2">
            {taxes.map((t, i) => (
              <Field key={t.uuid} label={t.name + (t.is_default ? " (default)" : "")} htmlFor={`tax-${i}`} required error={err(`tax_classes.${i}.rate_bps`)}>
                <div className="relative">
                  <input id={`tax-${i}`} inputMode="decimal" className={cn(inputClass, "pr-8")} value={t.rate} onChange={(e) => setTaxes(taxes.map((x, j) => (j === i ? { ...x, rate: e.target.value } : x)))} required />
                  <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">%</span>
                </div>
              </Field>
            ))}
          </div>
          <p className="text-xs text-muted-foreground">Confirm rates with your accountant before launch.</p>
        </Panel>

        <Panel title="Delivery charges & times" className="lg:col-span-2">
          <p className="text-sm text-muted-foreground">Set the charge, the free-delivery threshold and the delivery window (in working days) for each method. The window is shown to customers at checkout.</p>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-sm">
              <thead className="text-left text-xs font-semibold text-muted-foreground uppercase">
                <tr>
                  <th className="p-2">Method</th><th className="p-2">Zone</th>
                  <th className="p-2">Charge ₹<span className="text-red-600" aria-hidden="true">*</span></th>
                  <th className="p-2">Free over ₹</th>
                  <th className="p-2">From (days)<span className="text-red-600" aria-hidden="true">*</span></th>
                  <th className="p-2">To (days)<span className="text-red-600" aria-hidden="true">*</span></th>
                  <th className="p-2">Active</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {shipping.map((m, i) => (
                  <tr key={m.uuid} className="align-middle">
                    <td className="p-2 font-medium whitespace-nowrap">{m.name}</td>
                    <td className="p-2 capitalize">{m.zone}</td>
                    <td className="p-2"><input aria-label={`${m.name} charge (required)`} inputMode="decimal" className={inputClass} value={m.amountText} onChange={(e) => update(i, { amountText: e.target.value })} required /></td>
                    <td className="p-2"><input aria-label={`${m.name} free over`} inputMode="decimal" className={inputClass} placeholder="Never" value={m.freeText} onChange={(e) => update(i, { freeText: e.target.value })} /></td>
                    <td className="p-2">
                      <input aria-label={`${m.name} minimum days (required)`} type="number" min={0} max={60} className={inputClass} value={m.minText} onChange={(e) => update(i, { minText: e.target.value })} required />
                    </td>
                    <td className="p-2">
                      <input aria-label={`${m.name} maximum days (required)`} type="number" min={0} max={90} className={inputClass} value={m.maxText} onChange={(e) => update(i, { maxText: e.target.value })} required aria-invalid={err(`shipping_methods.${i}.days_max`) ? true : undefined} />
                      {err(`shipping_methods.${i}.days_max`) ? <p className="mt-1 text-xs text-destructive">Must be ≥ “From”.</p> : null}
                    </td>
                    <td className="p-2"><label className={checkboxLabelClass}><input aria-label={`${m.name} active`} type="checkbox" className="size-4 accent-primary" checked={m.is_active} onChange={(e) => update(i, { is_active: e.target.checked })} /><span className="sr-only">Active</span></label></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Panel>

        <PaymentsPanel integrations={settings.integrations} />
        <ShiprocketPanel shiprocket={settings.integrations.shiprocket} />

        <SecurityPanel security={settings.security} />
      </div>
    </AdminPage>
  );
}

function StatusLine({ ok, children }: { ok: boolean; children: React.ReactNode }) {
  const Icon = ok ? CircleCheck : CircleAlert;
  return <li className="flex items-start gap-2 text-sm"><Icon className={cn("mt-0.5 size-4 shrink-0", ok ? "text-emerald-700" : "text-amber-700")} aria-hidden /><span>{children}</span></li>;
}

function CopyField({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid gap-1.5">
      <span className="text-sm font-medium">{label}</span>
      <div className="flex items-center gap-2">
        <input readOnly aria-label={label} className={cn(inputClass, "font-mono text-xs")} value={value} onFocus={(e) => e.currentTarget.select()} />
        <Button type="button" size="sm" variant="outline" aria-label={`Copy ${label}`} onClick={() => { void navigator.clipboard?.writeText(value).then(() => toast.success("Copied.")); }}><Copy className="size-4" aria-hidden /></Button>
      </div>
    </div>
  );
}

/** Online payments only (no cash on delivery). Keys live in the server environment, never here. */
function PaymentsPanel({ integrations }: { integrations: StoreSettings["integrations"] }) {
  const rzp = integrations.razorpay;
  return (
    <Panel title="Online payments (Razorpay)">
      <p className="text-sm text-muted-foreground">Customers pay online by UPI, cards, net banking or wallets. Cash on delivery is not offered.</p>
      <ul className="grid gap-2">
        <StatusLine ok={rzp.configured}>{rzp.configured ? <>Razorpay is connected{rzp.test_mode ? <> in <strong>test mode</strong> (no real money)</> : <> in <strong>live mode</strong></>}.</> : <>Razorpay is not connected. Add <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> to the server environment.</>}</StatusLine>
        <StatusLine ok={rzp.webhook_configured}>{rzp.webhook_configured ? "Payment webhook secret is set." : <>Payment webhook not set up. Add <code>RAZORPAY_WEBHOOK_SECRET</code> so payments are confirmed even if a customer closes the page.</>}</StatusLine>
        {integrations.test_gateway ? <StatusLine ok={false}>The test payment option is visible at checkout because this is not the live site. It can never appear in production.</StatusLine> : null}
      </ul>
      <CopyField label="Razorpay webhook URL" value={rzp.webhook_url} />
      <p className="text-xs leading-5 text-muted-foreground">In the Razorpay Dashboard → Accounts & Settings → Webhooks, add this URL with the same secret, and tick <strong>payment.captured</strong>, <strong>payment.failed</strong> and <strong>order.paid</strong>.</p>
    </Panel>
  );
}

/** Shiprocket: packing an order books the pickup; Shiprocket's tracking webhooks move it on. */
function ShiprocketPanel({ shiprocket }: { shiprocket: StoreSettings["integrations"]["shiprocket"] }) {
  const test = useMutation({
    mutationFn: () => miscAdminApi.testShiprocket(),
    onSuccess: (r) => (r.pickup_location_found
      ? toast.success(`Connected to Shiprocket. Pickup addresses: ${r.pickup_locations.join(", ")}.`)
      : toast.warning(`Connected, but no pickup address is named "${shiprocket.pickup_location}". Found: ${r.pickup_locations.join(", ") || "none"}.`)),
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not reach Shiprocket."),
  });
  return (
    <Panel title="Delivery partner (Shiprocket)">
      <p className="text-sm text-muted-foreground">When you mark an order <strong>Packed</strong>, it is sent to Shiprocket, a courier is assigned and the pickup is booked. Shiprocket then updates the order to Shipped, Out for delivery and Delivered by itself.</p>
      <ul className="grid gap-2">
        <StatusLine ok={shiprocket.configured}>{shiprocket.configured ? <>Connected with pickup address <strong>{shiprocket.pickup_location}</strong>.</> : <>Not connected. Add <code>SHIPROCKET_EMAIL</code>, <code>SHIPROCKET_PASSWORD</code> (a Shiprocket API user) and <code>SHIPROCKET_PICKUP_LOCATION</code> to the server environment.</>}</StatusLine>
        <StatusLine ok={shiprocket.webhook_configured}>{shiprocket.webhook_configured ? "Tracking updates token is set." : <>Tracking updates are off. Add <code>SHIPROCKET_WEBHOOK_TOKEN</code> so status changes in Shiprocket reach this store.</>}</StatusLine>
      </ul>
      <CopyField label="Shiprocket webhook URL" value={shiprocket.webhook_url} />
      <p className="text-xs leading-5 text-muted-foreground">In Shiprocket → Settings → API → Webhooks, paste this URL and the same token (sent as <code>x-api-key</code>), then enable it.</p>
      <div><Button type="button" size="sm" variant="outline" disabled={!shiprocket.configured || test.isPending} onClick={() => test.mutate()}>Test connection</Button></div>
    </Panel>
  );
}

/** Staff 2FA requirement switch, confirmed with the current password. */
function SecurityPanel({ security }: { security: StoreSettings["security"] }) {
  const queryClient = useQueryClient();
  const [pending, setPending] = useState<boolean | null>(null);
  const [password, setPassword] = useState("");
  const enabled = security.admin_requires_2fa;
  const blockedEnable = !enabled && !security.you_have_2fa;

  const toggle = useMutation({
    mutationFn: (next: boolean) => miscAdminApi.saveSecurity({ admin_require_2fa: next, password }),
    onSuccess: (_, next) => {
      toast.success(next ? "Staff two-factor authentication is now required." : "Staff two-factor authentication is no longer required.");
      setPending(null); setPassword("");
      void queryClient.invalidateQueries({ queryKey: settingsKey });
    },
    onError: (e) => toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Could not change the setting."),
  });

  return (
    <Panel title="Security">
      <div className="flex items-start justify-between gap-4">
        <div className="grid gap-1">
          <p id="twofa-label" className="text-sm font-medium">Require two-factor authentication for staff</p>
          <p id="twofa-desc" className="text-xs text-muted-foreground">
            When on, staff must confirm sign-in with an authenticator app code before using the admin.
          </p>
        </div>
        <button
          type="button"
          role="switch"
          aria-checked={enabled}
          aria-labelledby="twofa-label"
          aria-describedby="twofa-desc"
          disabled={security.locked || toggle.isPending || (blockedEnable && pending === null)}
          onClick={() => setPending(!enabled)}
          className={cn(
            "relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50",
            enabled ? "bg-primary" : "bg-stone-300",
          )}
        >
          <span className={cn("inline-block size-6 rounded-full bg-white shadow transition-transform duration-300 ease-out", enabled ? "translate-x-5" : "translate-x-0")} />
        </button>
      </div>

      <p className="flex items-center gap-2 text-sm">
        {enabled ? <ShieldCheck className="size-4 text-emerald-700" aria-hidden /> : <ShieldOff className="size-4 text-red-600" aria-hidden />}
        Currently <strong>{enabled ? "required" : "not required"}</strong>
      </p>

      {security.locked ? (
        <p className="flex items-center gap-2 text-xs text-muted-foreground"><Lock className="size-3.5" aria-hidden /> Always on in production for the security of your store.</p>
      ) : null}
      {blockedEnable ? (
        <p className="text-xs text-muted-foreground">
          To turn this on, first <Link href="/account#security" className="underline underline-offset-4">set up two-factor on your own account</Link> — otherwise you would be locked out of the admin.
        </p>
      ) : null}

      {pending !== null ? (
        <form className="animate-expand grid items-start gap-3 rounded-sm border border-dashed border-border p-3 sm:grid-cols-[1fr_auto]" onSubmit={(e) => { e.preventDefault(); toggle.mutate(pending); }}>
          <Field label={`Confirm your password to turn ${pending ? "on" : "off"}`} htmlFor="twofa-password" required hint={pending ? undefined : "Owners and administrators get an email alert when this is turned off."}>
            <input id="twofa-password" type="password" autoComplete="current-password" className={inputClass} value={password} onChange={(e) => setPassword(e.target.value)} required autoFocus />
          </Field>
          <FormActions className="sm:pt-6">
            <Button type="submit" disabled={!password || toggle.isPending}>{pending ? "Turn on" : "Turn off"}</Button>
            <Button type="button" variant="ghost" onClick={() => { setPending(null); setPassword(""); }}>Cancel</Button>
          </FormActions>
        </form>
      ) : null}
    </Panel>
  );
}
