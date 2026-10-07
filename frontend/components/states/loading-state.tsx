import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/lib/i18n";

/** Skeleton block with an accessible status for screen readers. */
export function LoadingState({ lines = 3 }: { lines?: number }) {
  return (
    <div role="status" aria-live="polite" className="grid gap-3">
      <span className="sr-only">{t("common.loading")}</span>
      <Skeleton className="h-8 w-1/3" />
      {Array.from({ length: lines }, (_, i) => (
        <Skeleton key={i} className="h-5 w-full" />
      ))}
    </div>
  );
}
