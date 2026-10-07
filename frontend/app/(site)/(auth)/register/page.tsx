import type { Metadata } from "next";
import Link from "next/link";
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
          <Link href="/login" className="font-medium text-foreground underline-offset-4 hover:underline">
            {t("auth.login.title")}
          </Link>
        </>
      }
    >
      <RegisterForm />
    </AuthCard>
  );
}
