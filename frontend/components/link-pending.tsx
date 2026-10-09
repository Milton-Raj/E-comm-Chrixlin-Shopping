"use client";

import { Loader2 } from "lucide-react";
import { useLinkStatus } from "next/link";
import { cn } from "@/lib/utils";

/** Place inside a <Link>: a small spinner that shows while that link's page is loading. */
export function LinkPending({ className }: { className?: string }) {
  const { pending } = useLinkStatus();
  if (!pending) return null;
  return <Loader2 aria-hidden className={cn("link-pending size-3.5 shrink-0 animate-spin", className)} />;
}
