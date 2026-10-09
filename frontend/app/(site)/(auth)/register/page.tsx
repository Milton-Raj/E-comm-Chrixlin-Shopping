import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AuthSwitchLink } from "@/features/auth/components/auth-switch-link";
import { AuthCard } from "@/features/auth/components/auth-card";
import { RegisterForm } from "@/features/auth/components/register-form";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("auth.register.title"), robots: { index: false } };

export default function RegisterPage() {
  return (
    <AuthCard
      title={t("auth.register.title")}
      subtitle={t("auth.register.subtitle")}
      footer={
        <>
          {t("auth.register.haveAccount")}{" "}
          <Suspense fallback={null}>
            <AuthSwitchLink href="/login">{t("auth.login.title")}</AuthSwitchLink>
          </Suspense>
        </>
      }
    >
      <Suspense fallback={<LoadingState lines={4} />}>
        <RegisterForm />
      </Suspense>
    </AuthCard>
  );
}
