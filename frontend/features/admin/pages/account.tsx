"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { MailCheck, ShieldCheck, ShieldOff } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { api, ApiError } from "@/services/api-client";
import type { AdminMe } from "@/types/api";
import { adminMeKey } from "../components/admin-gate";
import { AdminPage, Field, FormActions, inputClass, Panel, RequiredNote } from "../components/kit/admin-page";

/** The signed-in staff member's own account: password (confirmed by an emailed code) and 2FA status. */
export function AdminAccountPage() {
  const me = useQueryClient().getQueryData<AdminMe>(adminMeKey);
  if (!me) return null;

  return (
    <AdminPage title="My account" description={`${me.user.name} · ${me.user.email} · ${me.roles.map((r) => r.replaceAll("-", " ")).join(", ")}`}>
      <div className="grid gap-4 lg:grid-cols-2">
        <ChangePasswordPanel email={me.user.email} />
        <Panel title="Two-factor sign-in">
          <p className="flex items-center gap-2 text-sm">
            {me.user.two_factor_enabled
              ? <><ShieldCheck className="size-4 text-emerald-700" aria-hidden /> On for your account — sign-in asks for a code from your authenticator app.</>
              : <><ShieldOff className="size-4 text-amber-700" aria-hidden /> Off for your account.</>}
          </p>
          <p className="text-sm text-muted-foreground">Set it up, turn it off or get new recovery codes on your account security page. Whether staff must use it is set in Settings → Security.</p>
          <div><Link href="/account#security" className="text-sm underline underline-offset-4">Manage two-factor sign-in</Link></div>
        </Panel>
      </div>
    </AdminPage>
  );
}

function ChangePasswordPanel({ email }: { email: string }) {
  const [sentTo, setSentTo] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const fail = (e: unknown) => {
    if (e instanceof ApiError) {
      setErrors(e.fieldErrors);
      toast.error(Object.values(e.fieldErrors)[0]?.[0] ?? e.message);
    } else toast.error("Something went wrong. Please try again.");
  };

  const send = useMutation({
    mutationFn: () => api.post<{ email: string; expires_in_minutes: number }>("/account/password/code"),
    onSuccess: (r) => { setSentTo(r.email); setErrors({}); setCode(""); toast.success(`Code sent to ${r.email}. It expires in ${r.expires_in_minutes} minutes.`); },
    onError: fail,
  });
  const change = useMutation({
    mutationFn: () => api.put("/account/password/with-code", { code, password, password_confirmation: confirm }),
    onSuccess: () => { toast.success("Password changed. Other devices have been signed out."); setSentTo(null); setCode(""); setPassword(""); setConfirm(""); setErrors({}); },
    onError: fail,
  });
  const err = (k: string) => errors[k]?.[0];
  const mismatch = confirm.length > 0 && confirm !== password;

  return (
    <Panel title="Change password" actions={sentTo ? <RequiredNote /> : null}>
      {!sentTo ? (
        <div className="grid gap-3">
          <p className="text-sm text-muted-foreground">For your security, we first email a 6-digit code to <strong className="text-foreground">{email}</strong>. You enter it with your new password.</p>
          <div><Button type="button" onClick={() => send.mutate()} disabled={send.isPending}><MailCheck className="size-4" aria-hidden />{send.isPending ? "Sending…" : "Email me a code"}</Button></div>
        </div>
      ) : (
        <form className="animate-expand grid gap-4" onSubmit={(e) => { e.preventDefault(); if (!mismatch) change.mutate(); }}>
          <p className="text-sm text-muted-foreground">We sent a code to <strong className="text-foreground">{sentTo}</strong>. Check your inbox (and spam folder).</p>
          <Field label="6-digit code" htmlFor="pw-code" required error={err("code")}>
            <input id="pw-code" className={`${inputClass} font-mono tracking-widest`} inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))} required />
          </Field>
          <Field label="New password" htmlFor="pw-new" required error={err("password")} hint="At least 8 characters.">
            <input id="pw-new" type="password" className={inputClass} autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </Field>
          <Field label="Confirm new password" htmlFor="pw-confirm" required error={mismatch ? "The passwords don't match." : undefined}>
            <input id="pw-confirm" type="password" className={inputClass} autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required />
          </Field>
          <FormActions>
            <Button type="submit" disabled={change.isPending || code.length !== 6 || !password || mismatch}>{change.isPending ? "Changing…" : "Change password"}</Button>
            <Button type="button" variant="ghost" onClick={() => send.mutate()} disabled={send.isPending}>Send a new code</Button>
          </FormActions>
        </form>
      )}
    </Panel>
  );
}
