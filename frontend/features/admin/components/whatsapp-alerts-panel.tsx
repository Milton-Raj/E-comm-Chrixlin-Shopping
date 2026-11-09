"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CircleAlert, CircleCheck, Copy, MessageCircle, Send } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type OrderAlertSettings } from "../api";
import { Field, FormActions, inputClass, Panel } from "./kit/admin-page";

const key = ["admin", "settings", "order-alerts"] as const;

/** The message template to create in Meta (Utility). It must not start or end with a variable. */
const TEMPLATE_BODY = `New order {{1}} received.
Amount: {{2}}
Items: {{3}}
Deliver to: {{4}}
Open it in your admin: {{5}}
Sent automatically by your store.`;

/** Admin → Settings: a WhatsApp message to the owner for every paid order. */
export function WhatsAppAlertsPanel() {
  const query = useQuery({ queryKey: key, queryFn: miscAdminApi.orderAlerts });
  if (query.isPending) return <Panel title="Order alerts on WhatsApp"><LoadingState lines={4} /></Panel>;
  if (query.isError) return <Panel title="Order alerts on WhatsApp"><ErrorState onRetry={() => void query.refetch()} /></Panel>;
  return <AlertsForm key={JSON.stringify(query.data)} data={query.data} />;
}

function AlertsForm({ data }: { data: OrderAlertSettings }) {
  const queryClient = useQueryClient();
  const [enabled, setEnabled] = useState(data.whatsapp_enabled);
  const [number, setNumber] = useState(data.whatsapp_number ?? "");
  const [error, setError] = useState<string | null>(null);
  const dirty = enabled !== data.whatsapp_enabled || number.replace(/\D/g, "") !== (data.whatsapp_number ?? "").replace(/\D/g, "");

  const save = useMutation({
    mutationFn: () => miscAdminApi.saveOrderAlerts({ whatsapp_enabled: enabled, whatsapp_number: number.trim() || null }),
    onSuccess: (fresh) => { setError(null); queryClient.setQueryData(key, fresh); toast.success("Order alert settings saved."); },
    onError: (e) => {
      const message = e instanceof ApiError ? (e.fieldErrors.whatsapp_number?.[0] ?? e.message) : "Could not save.";
      setError(message);
      toast.error(message);
    },
  });
  const test = useMutation({
    mutationFn: miscAdminApi.testOrderAlert,
    onSuccess: () => toast.success("Test message sent. Check your WhatsApp."),
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "The test message could not be sent."),
  });

  return (
    <Panel title="Order alerts on WhatsApp">
      <div className="flex items-start justify-between gap-4">
        <div className="grid gap-1">
          <p id="wa-label" className="text-sm font-medium">Send me a WhatsApp message for every new order</p>
          <p id="wa-desc" className="text-xs text-muted-foreground">Sent the moment a payment is confirmed: order number, amount, number of items and where it is going. No customer names or phone numbers are sent.</p>
        </div>
        <button
          type="button" role="switch" aria-checked={enabled} aria-labelledby="wa-label" aria-describedby="wa-desc"
          onClick={() => setEnabled(!enabled)}
          className={cn("relative inline-flex h-7 w-12 shrink-0 items-center rounded-full border-2 border-transparent transition-colors focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none", enabled ? "bg-primary" : "bg-stone-300")}
        >
          <span className={cn("inline-block size-6 rounded-full bg-white shadow transition-transform duration-300 ease-out", enabled ? "translate-x-5" : "translate-x-0")} />
        </button>
      </div>

      <Field label="WhatsApp number for alerts" htmlFor="wa-number" required={enabled} error={error ?? undefined} hint="With the country code, e.g. +91 98765 43210. It must have WhatsApp.">
        <input id="wa-number" type="tel" inputMode="tel" autoComplete="tel" className={inputClass} placeholder="+91 98765 43210" value={number} onChange={(e) => setNumber(e.target.value)} />
      </Field>

      <ul className="grid gap-2 text-sm">
        <li className="flex items-start gap-2">
          {data.connected ? <CircleCheck className="mt-0.5 size-4 shrink-0 text-emerald-700" aria-hidden /> : <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-700" aria-hidden />}
          <span>{data.connected ? <>WhatsApp is connected. Alerts use the message template <code>{data.template}</code>.</> : <>WhatsApp is not connected yet: follow the steps below, then add <code>WHATSAPP_ACCESS_TOKEN</code> and <code>WHATSAPP_PHONE_NUMBER_ID</code> to the server environment.</>}</span>
        </li>
      </ul>

      <FormActions>
        <Button type="button" onClick={() => save.mutate()} disabled={save.isPending || !dirty}>{save.isPending ? "Saving…" : "Save"}</Button>
        <Button type="button" variant="outline" onClick={() => test.mutate()} disabled={test.isPending || dirty || !data.connected || !data.whatsapp_number}>
          <Send className="size-4" aria-hidden /> {test.isPending ? "Sending…" : "Send test message"}
        </Button>
        {dirty ? <span className="text-xs text-muted-foreground">Save first, then send a test.</span> : null}
      </FormActions>

      <details className="border-t border-border pt-3 text-sm">
        <summary className="flex min-h-11 cursor-pointer list-none items-center gap-2 font-medium"><MessageCircle className="size-4 text-emerald-700" aria-hidden /> How to connect WhatsApp (one time, about 30 minutes)</summary>
        <ol className="mt-2 grid list-decimal gap-2 pl-5 text-muted-foreground">
          <li>Go to <strong className="text-foreground">business.facebook.com</strong> and create (or open) your Meta Business account, then at <strong className="text-foreground">developers.facebook.com</strong> create an app of type <strong className="text-foreground">Business</strong> and add <strong className="text-foreground">WhatsApp</strong>.</li>
          <li>Add the phone number the alerts will come <em>from</em>. It must not already be used in the WhatsApp app; a new SIM or a landline works. (While testing you can use Meta&apos;s free test number and add your own number as a recipient.)</li>
          <li>In WhatsApp Manager → <strong className="text-foreground">Message templates</strong>, create a template named <code>{data.template}</code>, category <strong className="text-foreground">Utility</strong>, language <strong className="text-foreground">English ({data.template_language})</strong>, with this text, and submit it for approval (usually minutes):
            <TemplateText />
          </li>
          <li>Create a <strong className="text-foreground">System user</strong> in Business settings with access to the app, generate a permanent token with <code>whatsapp_business_messaging</code> permission, and copy the <strong className="text-foreground">Phone number ID</strong> from the app&apos;s WhatsApp → API setup page.</li>
          <li>Give both to your developer to add on the server as <code>WHATSAPP_ACCESS_TOKEN</code> and <code>WHATSAPP_PHONE_NUMBER_ID</code> (never paste the token here).</li>
          <li>Switch alerts on above, save your number and press <strong className="text-foreground">Send test message</strong>.</li>
        </ol>
        <p className="mt-2 text-xs text-muted-foreground">Meta charges a small fee per alert (Utility messages to Indian numbers cost a fraction of a rupee); it is billed to the card on your Meta Business account.</p>
      </details>
    </Panel>
  );
}

function TemplateText() {
  return (
    <div className="mt-2 grid gap-2">
      <pre className="overflow-x-auto rounded-sm border border-border bg-muted/40 p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap text-foreground">{TEMPLATE_BODY}</pre>
      <div>
        <Button type="button" size="sm" variant="outline" onClick={() => { void navigator.clipboard?.writeText(TEMPLATE_BODY).then(() => toast.success("Template text copied.")); }}>
          <Copy className="size-4" aria-hidden /> Copy text
        </Button>
      </div>
    </div>
  );
}
