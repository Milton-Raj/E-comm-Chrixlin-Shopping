"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";
import { safeRedirectPath } from "@/lib/safe-redirect";
import { ApiError } from "@/services/api-client";
import { applyApiErrors } from "../form-errors";
import { useTwoFactorChallenge } from "../hooks";
import { twoFactorChallengeSchema } from "../schemas";

type Values = { code: string; recovery_code: string };

export function TwoFactorForm() {
  const router = useRouter();
  const next = safeRedirectPath(useSearchParams().get("next"));
  const challenge = useTwoFactorChallenge();
  const [useRecovery, setUseRecovery] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, reset, formState: { errors } } = useForm<Values>({
    resolver: zodResolver(twoFactorChallengeSchema(useRecovery)),
    defaultValues: { code: "", recovery_code: "" },
  });

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null);
    try {
      await challenge.mutateAsync(useRecovery ? { recovery_code: values.recovery_code } : { code: values.code });
      router.push(next);
    } catch (error) {
      if (error instanceof ApiError && error.code === "login_expired") {
        router.push(`/login?next=${encodeURIComponent(next)}`);
        return;
      }
      setFormError(applyApiErrors(error, setError, ["code", "recovery_code"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-4">
      <FormMessage message={formError} />
      {useRecovery ? (
        <TextField id="recovery_code" autoComplete="off" label={t("auth.twoFactor.recoveryCode")} error={errors.recovery_code?.message} {...register("recovery_code")} />
      ) : (
        <TextField id="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} label={t("auth.twoFactor.code")} error={errors.code?.message} {...register("code")} />
      )}
      <Button type="submit" size="lg" disabled={challenge.isPending}>
        {t("auth.twoFactor.submit")}
      </Button>
      <Button
        type="button"
        variant="link"
        onClick={() => {
          setUseRecovery((v) => !v);
          reset();
          setFormError(null);
        }}
      >
        {useRecovery ? t("auth.twoFactor.useCode") : t("auth.twoFactor.useRecovery")}
      </Button>
    </form>
  );
}
