import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { t } from "@/lib/i18n";
import type { Money } from "@/types/api";

export function Price({ price, compareAt, className, tone = "light" }: { price: Money; compareAt?: Money | null; className?: string; tone?: "light" | "dark" }) {
  const onSale = compareAt && compareAt.amount > price.amount;

  return (
    <p className={cn("flex flex-wrap items-baseline gap-x-2", className)}>
      <span className={cn(onSale && tone === "light" && "text-primary")}>{formatMoney(price)}</span>
      {onSale ? (
        <span className={cn("text-sm line-through", tone === "dark" ? "text-brand-grullo" : "text-muted-foreground")}>
          <span className="sr-only">{t("catalog.wasPrice", { price: "" })}</span>
          {formatMoney(compareAt)}
        </span>
      ) : null}
    </p>
  );
}
