"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";
import { authApi } from "../api";
import { applyApiErrors } from "../form-errors";
import { resetPasswordSchema } from "../schemas";

type Values = { password: string; password_confirmation: string };

export function ResetPasswordForm() {
  const router = useRouter();
  const params = useSearchParams();
  const token = params.get("token");
  const email = params.get("email");
  const reset = useMutation({ mutationFn: authApi.resetPassword });
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, formState: { errors } } = useForm<Values>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: "", password_confirmation: "" },
  });

  if (!token || !email) {
    return <FormMessage message={t("auth.reset.invalidLink")} />;
  }

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null);
    try {
      await reset.mutateAsync({ ...values, token, email });
      toast.success("Your password has been reset. Please sign in.");
      router.push("/login");
    } catch (error) {
      setFormError(applyApiErrors(error, setError, ["password", "password_confirmation"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-4">
      <FormMessage message={formError} />
      <TextField id="password" type="password" autoComplete="new-password" label={t("auth.password")} hint={t("validation.passwordMin")} error={errors.password?.message} {...register("password")} />
      <TextField id="password_confirmation" type="password" autoComplete="new-password" label={t("auth.passwordConfirm")} error={errors.password_confirmation?.message} {...register("password_confirmation")} />
      <Button type="submit" size="lg" disabled={reset.isPending}>
        {t("auth.reset.submit")}
      </Button>
    </form>
  );
}
