"use client";

import { Heart, Home, Search, ShoppingBag, Store } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import { t, type MessageKey } from "@/lib/i18n";
import { WishlistLink } from "@/features/catalog/components/wishlist-button";

const items: { href: string; label: MessageKey; icon: typeof Home }[] = [
  { href: "/", label: "nav.home", icon: Home },
  { href: "/shop", label: "nav.shop", icon: Store },
  { href: "/search", label: "nav.search", icon: Search },
  { href: "/wishlist", label: "nav.wishlist", icon: Heart },
  { href: "/cart", label: "nav.cart", icon: ShoppingBag },
];

/** Mobile bottom navigation (PRD §53); hidden from lg up where the header nav takes over. */
export function BottomNav() {
  const pathname = usePathname();

  return (
    <nav aria-label={t("nav.mobile")} className="fixed inset-x-0 bottom-0 z-40 border-t bg-background pb-[env(safe-area-inset-bottom)] lg:hidden">
      <ul className="mx-auto grid max-w-md grid-cols-5">
        {items.map(({ href, label, icon: Icon }) => {
          const active = href === "/" ? pathname === "/" : pathname.startsWith(href);
          const itemClass = cn(
            "flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs",
            active ? "font-semibold text-primary" : "text-muted-foreground",
          );
          if (href === "/wishlist") {
            return <li key={href}><WishlistLink className={itemClass}>{t(label)}</WishlistLink></li>;
          }
          return (
            <li key={href}>
              <Link
                href={href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex min-h-14 flex-col items-center justify-center gap-0.5 text-xs",
                  active ? "font-semibold text-primary" : "text-muted-foreground",
                )}
              >
                <Icon className="size-5" aria-hidden />
                {t(label)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
