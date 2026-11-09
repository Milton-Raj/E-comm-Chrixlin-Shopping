"use client";

import { Globe } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { useLocalization } from "../use-localization";

const DISMISSED_KEY = "delivery-notice-dismissed";

/** The visitor is somewhere the store does not deliver to while international selling is off. */
function useUndeliverable() {
  const { data } = useLocalization();
  return data && !data.delivers_to_visitor ? data : null;
}

function dismissedFor(): string | null {
  try {
    return window.localStorage.getItem(DISMISSED_KEY);
  } catch {
    return null; // without storage it shows each visit; the bag and checkout repeat it anyway
  }
}

/**
 * Shown once per visitor country, the first time they land on the store. Localization
 * loads only in the browser, so storage is read after hydration.
 */
export function DeliveryNoticeDialog() {
  const data = useUndeliverable();
  const [closed, setClosed] = useState(false);
  const open = Boolean(data?.detected_country) && !closed && dismissedFor() !== data?.detected_country;

  const close = () => {
    setClosed(true);
    try {
      if (data?.detected_country) window.localStorage.setItem(DISMISSED_KEY, data.detected_country);
    } catch {
      // ignore
    }
  };

  if (!data) return null;
  return (
    <Dialog open={open} onOpenChange={(next) => (next ? undefined : close())}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <Globe className="size-6 text-primary" aria-hidden />
          <DialogTitle>{t("intl.unavailableTitle")}</DialogTitle>
          <DialogDescription>{data.unavailable_message}</DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button onClick={close}>{t("intl.browse")}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/** Inline reminder in the bag and at checkout. */
export function DeliveryNotice({ className }: { className?: string }) {
  const data = useUndeliverable();
  if (!data) return null;

  return (
    <p role="status" className={cn("flex items-start gap-2 border-l-4 border-primary bg-muted/50 px-3 py-2 text-sm", className)}>
      <Globe className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden />
      <span>{data.unavailable_message}</span>
    </p>
  );
}
