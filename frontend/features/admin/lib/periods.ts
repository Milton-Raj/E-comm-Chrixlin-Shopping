/** Report periods: calendar dates as the person sees them (local), and Indian financial years (April–March). */

/** "2026-11-09" for a local date (not UTC, which can still be yesterday in India before 5:30 AM). */
export function localDate(d = new Date()): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

export const today = () => localDate();
export const thisMonth = () => localDate().slice(0, 7);
export const daysAgo = (days: number) => localDate(new Date(Date.now() - days * 86_400_000));

/** "2026-11" → first and last day of that month. */
export function monthRange(month: string): [string, string] {
  const [y, m] = month.split("-").map(Number) as [number, number];
  const last = new Date(Date.UTC(y, m, 0)).getUTCDate();
  return [`${month}-01`, `${month}-${String(last).padStart(2, "0")}`];
}

/** FY 2026–27 = 1 Apr 2026 – 31 Mar 2027 (`start` is the April year). */
export const fyRange = (start: number): [string, string] => [`${start}-04-01`, `${start + 1}-03-31`];
export const fyLabel = (start: number) => `FY ${start}–${String(start + 1).slice(2)}`;

export function currentFyStart(d = new Date()): number {
  return d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1;
}

/** Financial years from `first` to the current one, newest first. */
export function financialYears(first: number): number[] {
  const current = currentFyStart();
  return Array.from({ length: Math.max(1, current - first + 1) }, (_, i) => current - i);
}

/** The financial year a from/to pair covers exactly, if any. */
export function matchingFy(from: string, to: string): number | null {
  const start = Number(from.slice(0, 4));
  const [f, t] = fyRange(start);
  return f === from && t === to ? start : null;
}
