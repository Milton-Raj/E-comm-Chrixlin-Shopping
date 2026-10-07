"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { CheckboxField, FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";
import { safeRedirectPath } from "@/lib/safe-redirect";
import { applyApiErrors } from "../form-errors";
import { useLogin } from "../hooks";
import { loginSchema, type LoginInput } from "../schemas";

export function LoginForm() {
  const router = useRouter();
  const next = safeRedirectPath(useSearchParams().get("next"));
  const login = useLogin();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, formState: { errors } } = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "", remember: false },
  });

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null);
    try {
      const result = await login.mutateAsync(values);
      router.push(result.two_factor_required ? `/login/two-factor?next=${encodeURIComponent(next)}` : next);
    } catch (error) {
      setFormError(applyApiErrors(error, setError, ["email", "password"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-4">
      <FormMessage message={formError} />
      <TextField id="email" type="email" autoComplete="email" label={t("auth.email")} error={errors.email?.message} {...register("email")} />
      <TextField id="password" type="password" autoComplete="current-password" label={t("auth.password")} error={errors.password?.message} {...register("password")} />
      <div className="flex flex-wrap items-center justify-between gap-2">
        <CheckboxField id="remember" label={t("auth.remember")} {...register("remember")} />
        <Link href="/forgot-password" className="inline-flex min-h-11 items-center text-sm font-medium underline-offset-4 hover:underline">
          {t("auth.login.forgot")}
        </Link>
      </div>
      <Button type="submit" size="lg" disabled={login.isPending}>
        {t("auth.login.submit")}
      </Button>
    </form>
  );
}
