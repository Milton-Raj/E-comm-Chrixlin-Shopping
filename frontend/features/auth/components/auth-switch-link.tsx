"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { safeRedirectPath } from "@/lib/safe-redirect";

/** Link between sign in and create account that keeps the page the shopper came from. */
export function AuthSwitchLink({ href, children }: { href: "/login" | "/register"; children: React.ReactNode }) {
  const next = safeRedirectPath(useSearchParams().get("next"), "");
  return (
    <Link href={next ? `${href}?next=${encodeURIComponent(next)}` : href} className="font-medium text-foreground underline-offset-4 hover:underline">
      {children}
    </Link>
  );
}
