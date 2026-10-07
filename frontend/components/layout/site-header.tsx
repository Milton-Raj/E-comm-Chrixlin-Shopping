import { Search } from "lucide-react";
import Link from "next/link";
import { env } from "@/lib/env";
import { t } from "@/lib/i18n";
import { primaryNav } from "@/lib/navigation";
import { cn } from "@/lib/utils";
import { BagButton } from "@/features/cart/components/bag-link";
import { WishlistLink } from "@/features/catalog/components/wishlist-button";
import { AccountLink } from "./account-link";

const iconLink = "inline-flex size-11 items-center justify-center rounded-full transition-colors hover:bg-muted hover:text-primary";

export function SiteHeader() {
  return (
    <>
      <div className="bg-brand-black text-brand-cultured">
        <p className="eyebrow mx-auto max-w-7xl px-4 py-2.5 text-center">
          {t("announce.preview")}
          <span className="hidden md:inline"> · {t("announce.shipping")}</span>
        </p>
      </div>
      <header className="sticky top-0 z-40 border-b border-border bg-background/95 backdrop-blur">
        <div className="mx-auto grid h-16 max-w-7xl grid-cols-3 items-center px-4 md:h-20 md:px-6">
          <div className="flex items-center">
            <Link href="/search" className={iconLink} aria-label={t("nav.search")}>
              <Search className="size-5" aria-hidden />
            </Link>
          </div>
          <Link href="/" className="wordmark justify-self-center text-lg md:text-2xl">
            {env.storeName}
          </Link>
          <div className="flex items-center justify-end">
            <WishlistLink className={cn(iconLink, "hidden md:inline-flex")} />
            <AccountLink />
            <BagButton className={iconLink} />
          </div>
        </div>
        <nav aria-label={t("nav.primary")} className="hidden border-t border-border lg:block">
          <ul className="mx-auto flex max-w-7xl items-center justify-center gap-2 px-6">
            {primaryNav.map((item) => (
              <li key={item.href}>
                <Link href={item.href} className="eyebrow inline-flex min-h-11 items-center px-4 text-muted-foreground transition-colors hover:text-primary">
                  {t(item.label)}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      </header>
    </>
  );
}
