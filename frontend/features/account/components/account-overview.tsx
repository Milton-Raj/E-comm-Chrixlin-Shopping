"use client";

import { useMutation } from "@tanstack/react-query";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { ButtonLink } from "@/components/button-link";
import { Button } from "@/components/ui/button";
import { authApi } from "@/features/auth/api";
import { useCurrentUser, useLogout } from "@/features/auth/hooks";
import { t } from "@/lib/i18n";
import { ApiError } from "@/services/api-client";
import { TwoFactorPanel } from "./two-factor-panel";

export function AccountOverview() {
  const router = useRouter();
  const verified = useSearchParams().get("verified") === "1";
  const { data: user, isPending, error, refetch } = useCurrentUser();
  const logout = useLogout();
  const resend = useMutation({ mutationFn: authApi.resendVerification });
  // Signing out also clears the cached user; don't treat that as "guest → go to login".
  const signingOut = useRef(false);

  useEffect(() => {
    if (user === null && !signingOut.current) router.replace("/login?next=/account");
  }, [user, router]);

  useEffect(() => {
    if (verified) toast.success(t("account.verified"));
  }, [verified]);

  if (error) {
    return <ErrorState requestId={error instanceof ApiError ? error.requestId : null} onRetry={() => void refetch()} />;
  }

  if (isPending || !user) return <LoadingState lines={4} />;

  return (
    <div className="grid gap-8">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">{t("account.welcome", { name: user.name })}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{user.email}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          {user.is_staff ? (
            <ButtonLink variant="outline" href="/admin">{t("account.adminLink")}</ButtonLink>
          ) : null}
          <Button
            variant="ghost"
            disabled={logout.isPending}
            onClick={async () => {
              signingOut.current = true;
              await logout.mutateAsync().catch(() => undefined);
              router.replace("/");
            }}
          >
            {t("auth.signOut")}
          </Button>
        </div>
      </div>

      {!user.email_verified ? (
        <div role="status" className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/40 px-4 py-3 text-sm">
          <span>{t("account.verifyEmail")}</span>
          <Button
            variant="outline"
            disabled={resend.isPending}
            onClick={() => resend.mutate(undefined, { onSuccess: () => toast.success(t("account.verificationSent")) })}
          >
            {t("account.resendVerification")}
          </Button>
        </div>
      ) : null}

      <nav aria-label="Account" className="grid gap-3 sm:grid-cols-3">
        {[
          { href: "/orders", title: "Orders", body: "Track, view and manage your orders" },
          { href: "/downloads", title: "Downloads", body: "Your digital editions" },
          { href: "/wishlist", title: "Wishlist", body: "Pieces you've saved" },
        ].map((item) => (
          <Link key={item.href} href={item.href} className="grid gap-1 border border-border p-5 transition-colors hover:border-foreground">
            <span className="text-lg font-semibold">{item.title}</span>
            <span className="text-sm text-muted-foreground">{item.body}</span>
          </Link>
        ))}
      </nav>

      <section id="security" aria-labelledby="security-heading" className="rounded-2xl border p-5 md:p-6">
        <h2 id="security-heading" className="text-lg font-semibold">{t("account.security")}</h2>
        <h3 className="mt-4 text-sm font-semibold">{t("account.twoFactor")}</h3>
        <div className="mt-2">
          <TwoFactorPanel user={user} />
        </div>
      </section>
    </div>
  );
}
