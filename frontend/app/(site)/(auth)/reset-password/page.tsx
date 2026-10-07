import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AuthCard } from "@/features/auth/components/auth-card";
import { ResetPasswordForm } from "@/features/auth/components/reset-password-form";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("auth.reset.title"), robots: { index: false } };

export default function ResetPasswordPage() {
  return (
    <AuthCard title={t("auth.reset.title")}>
      <Suspense fallback={<LoadingState lines={2} />}>
        <ResetPasswordForm />
      </Suspense>
    </AuthCard>
  );
}
