import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AuthCard } from "@/features/auth/components/auth-card";
import { TwoFactorForm } from "@/features/auth/components/two-factor-form";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("auth.twoFactor.title"), robots: { index: false } };

export default function TwoFactorPage() {
  return (
    <AuthCard title={t("auth.twoFactor.title")} subtitle={t("auth.twoFactor.subtitle")}>
      <Suspense fallback={<LoadingState lines={1} />}>
        <TwoFactorForm />
      </Suspense>
    </AuthCard>
  );
}
