"use client";

import { t } from "@/lib/i18n";
import { useLocalization } from "../use-localization";

const names = typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "currency" }) : null;

/** Footer currency picker, shown only while the store sells abroad. */
export function CurrencySwitcher() {
  const { data, currency, setCurrency } = useLocalization();
  if (!data?.enabled || data.currencies.length < 2 || !currency) return null;

  return (
    <div className="grid gap-2 text-sm">
      <label htmlFor="currency" className="eyebrow text-brand-cultured">{t("footer.currency")}</label>
      <select
        id="currency"
        value={currency}
        onChange={(e) => setCurrency(e.target.value)}
        className="min-h-11 max-w-xs border border-brand-umber bg-brand-black px-3 text-brand-cultured focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none"
      >
        {data.currencies.map((c) => <option key={c.code} value={c.code}>{c.code} · {names?.of(c.code) ?? c.code}</option>)}
      </select>
      {currency !== data.store_currency ? (
        <p className="max-w-xs text-xs text-brand-grullo">{t("footer.currencyNote", { currency, base: data.store_currency })}</p>
      ) : null}
    </div>
  );
}

/** Attribution the free rate and IP-country data require, shown while they are in use. */
export function LocalizationCredits() {
  const { data } = useLocalization();
  if (!data?.enabled) return null;

  return (
    <span className="flex flex-wrap gap-x-4">
      <a href="https://www.exchangerate-api.com" target="_blank" rel="noopener noreferrer" className="underline-offset-4 hover:underline">{t("footer.ratesCredit")}</a>
      <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer" className="underline-offset-4 hover:underline">{t("footer.geoCredit")}</a>
    </span>
  );
}
