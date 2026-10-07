import type { Metadata } from "next";
import Link from "next/link";
import { AuthCard } from "@/features/auth/components/auth-card";
import { ForgotPasswordForm } from "@/features/auth/components/forgot-password-form";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("auth.forgot.title"), robots: { index: false } };

export default function ForgotPasswordPage() {
  return (
    <AuthCard
      title={t("auth.forgot.title")}
      subtitle={t("auth.forgot.subtitle")}
      footer={
        <Link href="/login" className="font-medium text-foreground underline-offset-4 hover:underline">
          {t("auth.forgot.backToLogin")}
        </Link>
      }
    >
      <ForgotPasswordForm />
    </AuthCard>
  );
}
