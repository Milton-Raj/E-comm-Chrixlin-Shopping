import type { Metadata } from "next";
import { AuthSwitchLink } from "@/features/auth/components/auth-switch-link";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AuthCard } from "@/features/auth/components/auth-card";
import { LoginForm } from "@/features/auth/components/login-form";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("auth.login.title"), robots: { index: false } };

export default function LoginPage() {
  return (
    <AuthCard
      title={t("auth.login.title")}
      subtitle={t("auth.login.subtitle")}
      footer={
        <>
          {t("auth.login.noAccount")}{" "}
          <Suspense fallback={null}>
            <AuthSwitchLink href="/register">{t("auth.login.createAccount")}</AuthSwitchLink>
          </Suspense>
        </>
      }
    >
      <Suspense fallback={<LoadingState lines={2} />}>
        <LoginForm />
      </Suspense>
    </AuthCard>
  );
}
