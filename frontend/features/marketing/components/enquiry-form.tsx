"use client";

import { useMutation } from "@tanstack/react-query";
import { CircleCheck } from "lucide-react";
import { useState, type FormEvent } from "react";
import { FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { t } from "@/lib/i18n";
import { api, ApiError } from "@/services/api-client";

type Fields = { name: string; company: string; email: string; phone: string; quantity: string; budget: string; occasion: string; needed_by: string; message: string; website: string };
const empty: Fields = { name: "", company: "", email: "", phone: "", quantity: "", budget: "", occasion: "", needed_by: "", message: "", website: "" };

/** Corporate & bulk gifting enquiry; the server validates everything again. */
export function EnquiryForm() {
  const [f, setF] = useState<Fields>(empty);
  const set = (k: keyof Fields) => (e: { target: { value: string } }) => setF((x) => ({ ...x, [k]: e.target.value }));
  const send = useMutation({
    mutationFn: () => api.post<{ uuid: string }>("/enquiries", {
      ...f, company: f.company || null, phone: f.phone || null, budget: f.budget || null, occasion: f.occasion || null,
      quantity: f.quantity ? Number(f.quantity) : null, needed_by: f.needed_by || null, website: f.website || null,
    }),
  });
  const fieldErrors = send.error instanceof ApiError ? send.error.fieldErrors : {};
  const err = (k: keyof Fields) => fieldErrors[k]?.[0];
  const formError = send.error ? (send.error instanceof ApiError && Object.keys(fieldErrors).length ? null : send.error instanceof ApiError ? send.error.message : t("gifting.error")) : null;

  if (send.isSuccess) {
    return (
      <div className="grid justify-items-start gap-3 border border-border bg-card p-6" role="status">
        <CircleCheck className="size-6 text-primary" aria-hidden />
        <p className="text-lg font-medium">{t("gifting.thanksTitle")}</p>
        <p className="text-muted-foreground">{t("gifting.thanksBody")}</p>
      </div>
    );
  }

  return (
    <form className="grid gap-4 border border-border bg-card p-6" noValidate onSubmit={(e: FormEvent) => { e.preventDefault(); send.mutate(); }}>
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField id="g-name" label={t("gifting.name")} required autoComplete="name" value={f.name} onChange={set("name")} error={err("name")} />
        <TextField id="g-company" label={t("gifting.company")} autoComplete="organization" value={f.company} onChange={set("company")} error={err("company")} />
        <TextField id="g-email" label={t("gifting.email")} type="email" required autoComplete="email" value={f.email} onChange={set("email")} error={err("email")} />
        <TextField id="g-phone" label={t("gifting.phone")} type="tel" autoComplete="tel" value={f.phone} onChange={set("phone")} error={err("phone")} />
        <TextField id="g-qty" label={t("gifting.quantity")} inputMode="numeric" value={f.quantity} onChange={(e) => setF((x) => ({ ...x, quantity: e.target.value.replace(/\D/g, "") }))} error={err("quantity")} />
        <TextField id="g-budget" label={t("gifting.budget")} placeholder={t("gifting.budgetPlaceholder")} value={f.budget} onChange={set("budget")} error={err("budget")} />
        <TextField id="g-occasion" label={t("gifting.occasion")} placeholder={t("gifting.occasionPlaceholder")} value={f.occasion} onChange={set("occasion")} error={err("occasion")} />
        <TextField id="g-date" label={t("gifting.neededBy")} type="date" value={f.needed_by} onChange={set("needed_by")} error={err("needed_by")} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor="g-message">{t("gifting.message")}</Label>
        <Textarea id="g-message" required className="min-h-32" placeholder={t("gifting.messagePlaceholder")} value={f.message} onChange={set("message")} aria-invalid={err("message") ? true : undefined} aria-describedby={err("message") ? "g-message-error" : undefined} />
        {err("message") ? <p id="g-message-error" className="text-sm text-destructive">{err("message")}</p> : null}
      </div>
      {/* Honeypot: hidden from people and screen readers; bots that fill it are rejected. */}
      <div aria-hidden="true" className="sr-only">
        <label htmlFor="g-website">Website</label>
        <input id="g-website" tabIndex={-1} autoComplete="off" value={f.website} onChange={set("website")} />
      </div>
      <FormMessage message={formError} />
      <div>
        <Button type="submit" size="lg" disabled={send.isPending}>{send.isPending ? t("gifting.sending") : t("gifting.submit")}</Button>
      </div>
      <p className="text-xs text-muted-foreground">{t("gifting.privacy")}</p>
    </form>
  );
}
