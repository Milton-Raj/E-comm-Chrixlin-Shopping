import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { t } from "@/lib/i18n";
import type { Money } from "@/types/api";
import { savings } from "../catalog";

/** Offer price, the struck-through MRP and how much the shopper saves. */
export function Price({ price, compareAt, className, tone = "light" }: { price: Money; compareAt?: Money | null; className?: string; tone?: "light" | "dark" }) {
  const saved = savings({ price, compare_at_price: compareAt ?? null });

  return (
    <div className={className}>
      <p className="flex flex-wrap items-baseline gap-x-2">
        <span className={cn(saved && tone === "light" && "text-primary")}>{formatMoney(price)}</span>
        {saved && compareAt ? (
          <span className={cn("text-sm line-through", tone === "dark" ? "text-brand-grullo" : "text-muted-foreground")}>
            <span className="sr-only">{t("catalog.wasPrice", { price: "" })}</span>
            {formatMoney(compareAt)}
          </span>
        ) : null}
      </p>
      {saved ? (
        <p className={cn("mt-0.5 text-sm font-medium", tone === "dark" ? "text-brand-grullo" : "text-primary")}>{t("catalog.youSave", { amount: formatMoney(saved) })}</p>
      ) : null}
    </div>
  );
}
