import type { Metadata } from "next";
import Link from "next/link";
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
          <Link href="/register" className="font-medium text-foreground underline-offset-4 hover:underline">
            {t("auth.login.createAccount")}
          </Link>
        </>
      }
    >
      <Suspense fallback={<LoadingState lines={2} />}>
        <LoginForm />
      </Suspense>
    </AuthCard>
  );
}
