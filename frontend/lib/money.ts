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

export type CurrencyRate = { code: string; rate: string; exponent: number };

/**
 * Converts store-currency money at a decimal-string rate with exact integer maths,
 * rounding half-up to the target's minor unit, so it matches the API (Presentment).
 */
export function convertMoney(money: Money, fromExponent: number, to: CurrencyRate): Money {
  const [whole = "0", fraction = ""] = to.rate.split(".");
  const rate = BigInt(whole + fraction);
  const numerator = BigInt(money.amount) * rate * 10n ** BigInt(to.exponent);
  const denominator = 10n ** BigInt(fraction.length + fromExponent);
  const sign = numerator < 0n ? -1n : 1n;
  const rounded = (sign * numerator * 2n + denominator) / (2n * denominator);

  return { amount: Number(sign * rounded), currency: to.code };
}
