"use client";

import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";

/** User-facing error (PRD §86): friendly copy plus a reference id support can trace in logs. */
export function ErrorState({
  title = t("common.errorTitle"),
  description = t("common.errorBody"),
  requestId,
  onRetry,
}: {
  title?: string;
  description?: string;
  requestId?: string | null;
  onRetry?: () => void;
}) {
  return (
    <div role="alert" className="flex flex-col items-center justify-center gap-3 rounded-xl border px-6 py-12 text-center">
      <h2 className="text-lg font-semibold">{title}</h2>
      <p className="max-w-md text-sm text-muted-foreground">{description}</p>
      {requestId ? (
        <p className="font-mono text-xs text-muted-foreground">{t("common.reference", { id: requestId })}</p>
      ) : null}
      {onRetry ? (
        <Button variant="outline" onClick={onRetry} className="mt-2">
          {t("common.retry")}
        </Button>
      ) : null}
    </div>
  );
}
