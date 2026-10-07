"use client";

import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { ButtonLink } from "@/components/button-link";
import { t } from "@/lib/i18n";
import { api, ApiError } from "@/services/api-client";
import type { AdminMe } from "@/types/api";
import { AdminShell } from "./admin-shell";

export const adminMeKey = ["admin", "me"] as const;

/**
 * Loads /admin/me and routes each failure to the right state:
 * 401 → login, 403 two_factor_required → set up 2FA, 403 → no access.
 */
export function AdminGate({ children }: { children: ReactNode }) {
  const router = useRouter();
  const { data, error, isPending, refetch } = useQuery({
    queryKey: adminMeKey,
    queryFn: () => api.get<AdminMe>("/admin/me"),
  });

  const status = error instanceof ApiError ? error.status : null;

  useEffect(() => {
    if (status === 401) router.replace("/login?next=/admin");
  }, [status, router]);

  if (isPending || status === 401) {
    return <div className="mx-auto max-w-5xl p-6"><LoadingState lines={6} /></div>;
  }

  if (error instanceof ApiError && error.code === "two_factor_required") {
    return (
      <Centered title={t("admin.twoFactorTitle")} body={t("admin.twoFactorBody")}>
        <ButtonLink href="/account#security">{t("account.twoFactorEnable")}</ButtonLink>
      </Centered>
    );
  }

  if (status === 403) {
    return (
      <Centered title={t("admin.noAccessTitle")} body={t("admin.noAccessBody")}>
        <ButtonLink variant="outline" href="/">{t("common.backHome")}</ButtonLink>
      </Centered>
    );
  }

  if (error || !data) {
    return (
      <div className="mx-auto max-w-xl p-6">
        <ErrorState requestId={error instanceof ApiError ? error.requestId : null} onRetry={() => void refetch()} />
      </div>
    );
  }

  return <AdminShell me={data}>{children}</AdminShell>;
}

function Centered({ title, body, children }: { title: string; body: string; children: ReactNode }) {
  return (
    <div className="mx-auto flex min-h-[60vh] max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
      <h1 className="text-xl font-semibold">{title}</h1>
      <p className="text-sm text-muted-foreground">{body}</p>
      <div className="mt-2">{children}</div>
    </div>
  );
}
