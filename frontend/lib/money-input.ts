/**
 * Converts between rupee strings typed by admins and integer minor units (PRD §84),
 * using string arithmetic only — never floats.
 */
export function toMinorUnits(input: string, exponent = 2): number | null {
  const cleaned = input.replace(/[,\s₹]/g, "");
  if (cleaned === "") return null;
  const match = /^(\d{1,12})(?:\.(\d*))?$/.exec(cleaned);
  if (!match) return null;
  const whole = match[1]!;
  const fraction = (match[2] ?? "").padEnd(exponent, "0");
  if (fraction.length > exponent) return null;
  return Number(whole) * 10 ** exponent + Number(fraction || "0");
}

export function fromMinorUnits(amount: number | null | undefined, exponent = 2): string {
  if (amount === null || amount === undefined) return "";
  const negative = amount < 0;
  const abs = Math.abs(amount);
  const whole = Math.floor(abs / 10 ** exponent);
  const fraction = String(abs % 10 ** exponent).padStart(exponent, "0");
  return `${negative ? "-" : ""}${whole}${exponent ? `.${fraction}` : ""}`;
}

/** Parses a discount typed as a percentage ("20", "12.5") into basis points (0–9999), or null if invalid. */
export function percentToBasisPoints(input: string): number | null {
  const cleaned = input.replace(/[\s%]/g, "");
  if (cleaned === "") return 0;
  const match = /^(\d{1,2})(?:\.(\d{0,2}))?$/.exec(cleaned);
  if (!match) return null;
  return Number(match[1]) * 100 + Number((match[2] ?? "").padEnd(2, "0"));
}

/** Selling price after taking `basisPoints` off the MRP, rounded to the nearest minor unit. */
export function applyPercentOff(mrp: number, basisPoints: number): number {
  return mrp - Math.round((mrp * basisPoints) / 10_000);
}

/** The discount (as an input string, e.g. "12.5") that turns `mrp` into `price`; "" when there is none. */
export function percentOff(price: number, mrp: number | null | undefined): string {
  if (!mrp || mrp <= price) return "";
  const bp = Math.round(((mrp - price) * 10_000) / mrp);
  return String(bp / 100);
}
