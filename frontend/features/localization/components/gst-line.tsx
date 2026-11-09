import { t } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";
import type { Money } from "@/types/api";

/**
 * "Includes … GST" under a total. Shoppers paying in another currency see it as Indian GST,
 * in their currency, with a line explaining why an Indian tax applies.
 */
export function GstLine({ tax, breakdown, abroad }: { tax: Money; breakdown: { code: string; amount: Money }[]; abroad: boolean }) {
  if (tax.amount <= 0) return null;
  const parts = breakdown.length ? ` (${breakdown.map((b) => `${b.code} ${formatMoney(b.amount)}`).join(", ")})` : "";
  const vars = { amount: formatMoney(tax), breakdown: parts };

  return (
    <div className="grid gap-1 text-xs text-muted-foreground">
      <p>{t(abroad ? "intl.indianGstIncluded" : "intl.gstIncluded", vars)}</p>
      {abroad ? <p>{t("intl.indianGstNote")}</p> : null}
    </div>
  );
}
