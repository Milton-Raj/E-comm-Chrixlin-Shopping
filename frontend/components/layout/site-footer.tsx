import { cacheLife } from "next/cache";
import Link from "next/link";
import { env } from "@/lib/env";
import { t } from "@/lib/i18n";
import { footerNav } from "@/lib/navigation";

export function SiteFooter() {
  return (
    <footer className="mt-24 bg-brand-black pb-20 text-brand-cultured lg:pb-0">
      <div className="mx-auto grid max-w-7xl gap-10 px-4 py-16 md:grid-cols-4 md:px-6">
        <div className="grid content-start gap-4">
          <p className="wordmark text-xl">{env.storeName}</p>
          <p className="max-w-xs text-sm text-brand-grullo">{t("home.heroBody")}</p>
        </div>
        {footerNav.map((group) => (
          <nav key={group.title} aria-label={t(group.title)}>
            <h2 className="eyebrow text-brand-cultured">{t(group.title)}</h2>
            <ul className="mt-4 grid gap-1">
              {group.items.map((item) => (
                <li key={item.href}>
                  <Link href={item.href} className="inline-flex min-h-11 items-center text-sm text-brand-grullo transition-colors hover:text-brand-cultured md:min-h-8">
                    {t(item.label)}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        ))}
      </div>
      <div className="border-t border-brand-umber/60">
        <p className="mx-auto max-w-7xl px-4 py-6 text-xs text-brand-grullo md:px-6">
          <Copyright />
        </p>
      </div>
    </footer>
  );
}

/** Current year, cached for a day so the footer stays in the static shell. */
async function Copyright() {
  "use cache";
  cacheLife("days");

  return <>{t("footer.rights", { year: new Date().getFullYear(), store: env.storeName })}</>;
}
