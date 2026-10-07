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
