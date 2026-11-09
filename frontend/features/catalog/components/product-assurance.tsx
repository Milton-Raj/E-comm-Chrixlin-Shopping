import { HandHeart, MessageCircle, Package, Share2, ShieldCheck, Truck } from "lucide-react";
import type { ReactNode } from "react";
import { env } from "@/lib/env";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { MoneyText } from "@/features/localization/components/money-text";
import { productHref } from "../catalog";
import { siteText, type SiteContent } from "../site-content";
import type { ProductDetail } from "../types";

/** Reassurance points shown under the buy buttons. */
export function ProductAssurance({ product, content }: { product: ProductDetail; content: SiteContent }) {
  const physical = product.product_type === "physical";
  // With a single delivery method there is nothing to choose at checkout, so it is shown here instead.
  const only = physical && content.delivery_options?.length === 1 ? content.delivery_options[0] : null;
  // Prices go through MoneyText so shoppers abroad see them in their own currency.
  const [before, after = ""] = only ? t("catalog.deliveryLine", { name: only.name, price: "{price}", days: only.days_min === only.days_max ? String(only.days_max) : `${only.days_min}–${only.days_max}` }).split("{price}") : [];
  const [freeBefore, freeAfter = ""] = t("catalog.deliveryFreeOver", { amount: "{amount}" }).split("{amount}");
  const delivery = only
    ? [{
        icon: Truck,
        key: "delivery",
        text: (
          <>
            {before}{only.amount.amount === 0 ? t("catalog.deliveryFree") : <MoneyText money={only.amount} />}{after}
            {only.free_over && only.amount.amount > 0 ? <> · {freeBefore}<MoneyText money={only.free_over} />{freeAfter}</> : null}
          </>
        ),
      }]
    : [];
  const points: { icon: typeof Truck; key: string; text: ReactNode }[] = [
    ...delivery,
    ...[
      ...(physical ? [{ icon: HandHeart, text: siteText(content, "catalog.trustHandmade") }] : []),
      { icon: ShieldCheck, text: siteText(content, "catalog.trustSecure") },
      ...(physical ? [{ icon: Package, text: siteText(content, "catalog.trustPacked") }] : []),
    ].filter((p) => p.text).map((p) => ({ ...p, key: p.text })),
  ];

  return (
    <ul className="mt-4 grid gap-1.5 text-sm text-muted-foreground">
      {points.map(({ icon: Icon, key, text }) => (
        <li key={key} className="flex items-start gap-2.5"><Icon className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden /><span>{text}</span></li>
      ))}
    </ul>
  );
}

/** WhatsApp share / ask links (plain links: no JS needed), shown beside the rating. */
export function ProductShareLinks({ product, content, className }: { product: ProductDetail; content: SiteContent; className?: string }) {
  const url = `${env.siteUrl}${productHref(product)}`;
  const share = `https://wa.me/?text=${encodeURIComponent(t("catalog.whatsappShareText", { name: product.name, url }))}`;
  const ask = content.whatsapp_number ? `https://wa.me/${content.whatsapp_number}?text=${encodeURIComponent(t("catalog.whatsappAskText", { name: product.name, url }))}` : null;

  return (
    <div className={cn("flex flex-wrap gap-x-5 text-sm", className)}>
      <a href={share} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center gap-2 underline-offset-4 hover:text-primary hover:underline">
        <Share2 className="size-4" aria-hidden />{t("catalog.whatsappShare")}<span className="sr-only"> {t("common.opensNewTab")}</span>
      </a>
      {ask ? (
        <a href={ask} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center gap-2 underline-offset-4 hover:text-primary hover:underline">
          <MessageCircle className="size-4" aria-hidden />{t("catalog.whatsappAsk")}<span className="sr-only"> {t("common.opensNewTab")}</span>
        </a>
      ) : null}
    </div>
  );
}
