"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ShieldCheck } from "lucide-react";
import { useState } from "react";
import { FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { currentUserKey } from "@/features/auth/hooks";
import { t } from "@/lib/i18n";
import { ApiError } from "@/services/api-client";
import type { TwoFactorSetup, User } from "@/types/api";
import { accountApi } from "../api";

function errorText(error: unknown): string {
  if (error instanceof ApiError) return Object.values(error.fieldErrors)[0]?.[0] ?? error.message;
  return "Something went wrong. Please try again.";
}

/** Two-step 2FA setup: confirm password → scan QR → confirm code → show recovery codes once. */
export function TwoFactorPanel({ user }: { user: User }) {
  const queryClient = useQueryClient();
  const [step, setStep] = useState<"idle" | "password" | "scan" | "codes">("idle");
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [setup, setSetup] = useState<TwoFactorSetup | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const start = useMutation({ mutationFn: accountApi.startTwoFactor });
  const confirm = useMutation({ mutationFn: accountApi.confirmTwoFactor });

  if (user.two_factor_enabled && step !== "codes") {
    return (
      <p className="flex items-center gap-2 text-sm">
        <ShieldCheck className="size-5 text-emerald-600" aria-hidden />
        {t("account.twoFactorOn")}
      </p>
    );
  }

  return (
    <div className="grid gap-4">
      {step === "idle" ? (
        <>
          <p className="text-sm text-muted-foreground">{t("account.twoFactorOff")}</p>
          {user.is_staff ? <p className="text-sm font-medium">{t("account.twoFactorStaffRequired")}</p> : null}
          <div>
            <Button onClick={() => setStep("password")}>{t("account.twoFactorEnable")}</Button>
          </div>
        </>
      ) : null}

      {step === "password" ? (
        <form
          className="grid max-w-sm gap-3"
          onSubmit={async (event) => {
            event.preventDefault();
            setError(null);
            try {
              setSetup(await start.mutateAsync(password));
              setPassword("");
              setStep("scan");
            } catch (e) {
              setError(errorText(e));
            }
          }}
        >
          <FormMessage message={error} />
          <TextField id="confirm-password" type="password" autoComplete="current-password" label={t("account.confirmPassword")} value={password} onChange={(e) => setPassword(e.target.value)} />
          <div>
            <Button type="submit" disabled={start.isPending || password === ""}>{t("account.continue")}</Button>
          </div>
        </form>
      ) : null}

      {step === "scan" && setup ? (
        <form
          className="grid max-w-sm gap-3"
          onSubmit={async (event) => {
            event.preventDefault();
            setError(null);
            try {
              const result = await confirm.mutateAsync(code);
              setRecoveryCodes(result.recovery_codes);
              setStep("codes");
            } catch (e) {
              setError(errorText(e));
            }
          }}
        >
          <p className="text-sm">{t("account.twoFactorScan")}</p>
          {/* QR SVG is generated server-side by bacon-qr-code from our own data, never user HTML. */}
          <div className="w-48 rounded-lg border bg-white p-2" aria-hidden dangerouslySetInnerHTML={{ __html: setup.qr_svg }} />
          <p className="break-all font-mono text-xs text-muted-foreground">{t("account.twoFactorSecret", { secret: setup.secret })}</p>
          <FormMessage message={error} />
          <TextField id="two-factor-code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} label={t("auth.twoFactor.code")} value={code} onChange={(e) => setCode(e.target.value)} />
          <div>
            <Button type="submit" disabled={confirm.isPending || code.length !== 6}>{t("account.twoFactorConfirm")}</Button>
          </div>
        </form>
      ) : null}

      {step === "codes" ? (
        <div className="grid max-w-md gap-3">
          <h3 className="font-semibold">{t("account.recoveryCodesTitle")}</h3>
          <p className="text-sm text-muted-foreground">{t("account.recoveryCodesBody")}</p>
          <ul className="grid grid-cols-2 gap-2 rounded-lg border bg-muted/40 p-4 font-mono text-sm">
            {recoveryCodes.map((c) => <li key={c}>{c}</li>)}
          </ul>
          <div>
            <Button
              onClick={() => {
                setRecoveryCodes([]);
                setStep("idle");
                void queryClient.invalidateQueries({ queryKey: currentUserKey });
              }}
            >
              {t("account.recoveryCodesDone")}
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
