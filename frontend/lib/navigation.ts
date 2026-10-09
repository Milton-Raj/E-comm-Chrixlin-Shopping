import type { MessageKey } from "@/lib/i18n";

/** Storefront navigation (PRD §9, §53). */
export type NavItem = { href: string; label: MessageKey };

export const primaryNav: NavItem[] = [
  { href: "/shop", label: "nav.shop" },
  { href: "/categories", label: "nav.categories" },
  { href: "/shop?sort=newest", label: "nav.newArrivals" },
  { href: "/shop?sort=best_selling", label: "nav.bestSellers" },
  { href: "/search?q=candle", label: "nav.dessertCandles" },
  { href: "/search?q=jesmonite", label: "nav.jesmonite" },
];

export const footerNav: { title: MessageKey; items: NavItem[] }[] = [
  { title: "footer.shop", items: [{ href: "/shop", label: "nav.shop" }, { href: "/categories", label: "nav.categories" }, { href: "/search?q=candle", label: "nav.dessertCandles" }, { href: "/search?q=jesmonite", label: "nav.jesmonite" }] },
  { title: "footer.help", items: [{ href: "/pages/contact", label: "footer.contact" }, { href: "/faq", label: "footer.faq" }, { href: "/pages/shipping", label: "footer.shipping" }, { href: "/pages/returns", label: "footer.returns" }] },
  { title: "footer.legal", items: [{ href: "/pages/privacy", label: "footer.privacy" }, { href: "/pages/terms", label: "footer.terms" }, { href: "/pages/refund-policy", label: "footer.refunds" }] },
];
