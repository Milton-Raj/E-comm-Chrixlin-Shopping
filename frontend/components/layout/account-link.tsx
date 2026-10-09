"use client";

import { UserRound } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { currentPathForReturn, safeRedirectPath } from "@/lib/safe-redirect";
import { useCurrentUser } from "@/features/auth/hooks";
import { t } from "@/lib/i18n";

/** Client-side so the header itself stays in the static shell (cacheComponents). */
export function AccountLink() {
  const { data: user } = useCurrentUser();
  const router = useRouter();
  const label = user ? t("nav.account") : t("nav.signIn");

  return (
    <Link
      href={user ? "/account" : "/login"}
      className="inline-flex size-11 items-center justify-center rounded-full transition-colors hover:bg-muted hover:text-primary"
      aria-label={label}
      onClick={(e) => {
        if (user) return;
        // Remember the page so sign-in (or sign-up) brings the shopper straight back to it.
        const back = safeRedirectPath(currentPathForReturn(), "");
        if (!back || back === "/") return;
        e.preventDefault();
        router.push(`/login?next=${encodeURIComponent(back)}`);
      }}
    >
      <UserRound className="size-5" aria-hidden />
    </Link>
  );
}
