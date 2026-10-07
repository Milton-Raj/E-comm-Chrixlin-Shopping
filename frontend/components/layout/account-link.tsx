"use client";

import { UserRound } from "lucide-react";
import Link from "next/link";
import { useCurrentUser } from "@/features/auth/hooks";
import { t } from "@/lib/i18n";

/** Client-side so the header itself stays in the static shell (cacheComponents). */
export function AccountLink() {
  const { data: user } = useCurrentUser();
  const label = user ? t("nav.account") : t("nav.signIn");

  return (
    <Link
      href={user ? "/account" : "/login"}
      className="inline-flex size-11 items-center justify-center rounded-full transition-colors hover:bg-muted hover:text-primary"
      aria-label={label}
    >
      <UserRound className="size-5" aria-hidden />
    </Link>
  );
}
