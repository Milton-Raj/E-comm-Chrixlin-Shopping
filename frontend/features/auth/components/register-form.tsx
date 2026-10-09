"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { CheckboxField, FormMessage, TextField } from "@/components/form-field";
import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";
import { safeRedirectPath } from "@/lib/safe-redirect";
import { applyApiErrors } from "../form-errors";
import { useRegister } from "../hooks";
import { registerSchema, type RegisterInput } from "../schemas";

export function RegisterForm() {
  const router = useRouter();
  // Back to the product (or checkout) the shopper was on; their account page otherwise.
  const next = safeRedirectPath(useSearchParams().get("next"));
  const registerUser = useRegister();
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, setError, formState: { errors } } = useForm<RegisterInput>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: "", email: "", password: "", password_confirmation: "", marketing_opt_in: false },
  });

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null);
    try {
      await registerUser.mutateAsync(values);
      router.push(next);
    } catch (error) {
      setFormError(applyApiErrors(error, setError, ["name", "email", "password", "password_confirmation"]));
    }
  });

  return (
    <form onSubmit={onSubmit} noValidate className="grid gap-4">
      <FormMessage message={formError} />
      <TextField id="name" autoComplete="name" label={t("auth.name")} error={errors.name?.message} {...register("name")} />
      <TextField id="email" type="email" autoComplete="email" label={t("auth.email")} error={errors.email?.message} {...register("email")} />
      <TextField id="password" type="password" autoComplete="new-password" label={t("auth.password")} hint={t("validation.passwordMin")} error={errors.password?.message} {...register("password")} />
      <TextField id="password_confirmation" type="password" autoComplete="new-password" label={t("auth.passwordConfirm")} error={errors.password_confirmation?.message} {...register("password_confirmation")} />
      <CheckboxField id="marketing_opt_in" label={t("auth.register.marketing")} {...register("marketing_opt_in")} />
      <Button type="submit" size="lg" disabled={registerUser.isPending}>
        {t("auth.register.submit")}
      </Button>
    </form>
  );
}
