"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";
import { authApi } from "../api";
import { applyApiErrors } from "../form-errors";
import { forgotPasswordSchema } from "../schemas";

export function ForgotPasswordForm() {
  const forgot = useMutation({ mutationFn: authApi.forgotPassword });
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const { register, handleSubmit, setError, formState: { errors } } = useForm<{ email: string }>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: "" },
  });

  const onSubmit = handleSubmit(async (values) => {
    setMessage(null);
    try {
      await forgot.mutateAsync(values);
      setMessage({ ok: true, text: "If an account exists for that email, a reset link has been sent." });
    } catch (error) {
      const text = applyApiErrors(error, setError, ["email"]);
      setMessage(text ? { ok: false, text } : null);
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-4">
      {message?.ok ? (
        <p role="status" className="rounded-lg border bg-muted px-3 py-2 text-sm">{message.text}</p>
      ) : (
        <FormMessage message={message?.text ?? null} />
      )}
      <TextField id="email" type="email" autoComplete="email" label={t("auth.email")} error={errors.email?.message} {...register("email")} />
      <Button type="submit" size="lg" disabled={forgot.isPending}>
        {t("auth.forgot.submit")}
      </Button>
    </form>
  );
}
