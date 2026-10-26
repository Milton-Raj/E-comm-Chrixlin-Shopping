import type { Money } from "@/types/api";

/**
 * Formats integer minor units (PRD §84). The currency's own exponent comes
 * from Intl, so nothing assumes 2 decimals or a ₹ symbol (PRD §83).
 */
export function formatMoney(money: Money, locale = "en-IN"): string {
  const formatter = new Intl.NumberFormat(locale, {
    style: "currency",
    currency: money.currency,
  });
  const exponent = formatter.resolvedOptions().maximumFractionDigits ?? 2;

  return formatter.format(money.amount / 10 ** exponent);
}

/** Plain decimal amount for machine-readable formats (schema.org, product feeds): "2499.00". */
export function moneyToDecimal(money: Money): string {
  const exponent = new Intl.NumberFormat("en", { style: "currency", currency: money.currency }).resolvedOptions().maximumFractionDigits ?? 2;

  return (money.amount / 10 ** exponent).toFixed(exponent);
}
