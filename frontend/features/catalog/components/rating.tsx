import { Star } from "lucide-react";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";

export function Rating({ average, count, className }: { average: number; count: number; className?: string }) {
  return (
    <div className={cn("flex items-center gap-1.5 text-xs text-muted-foreground", className)}>
      <span className="flex" role="img" aria-label={t("catalog.ratingLabel", { rating: average.toFixed(1) })}>
        {Array.from({ length: 5 }, (_, i) => (
          <Star
            key={i}
            aria-hidden
            className={cn("size-3.5", i < Math.round(average) ? "fill-brand-grullo text-brand-grullo" : "text-border")}
          />
        ))}
      </span>
      <span>
        {average.toFixed(1)} · {t("catalog.reviews", { count })}
      </span>
    </div>
  );
}
