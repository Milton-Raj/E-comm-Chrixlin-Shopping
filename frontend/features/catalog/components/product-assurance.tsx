import { HandHeart, MessageCircle, Package, Share2, ShieldCheck } from "lucide-react";
import { env } from "@/lib/env";
import { t } from "@/lib/i18n";
import { productHref } from "../catalog";
import { siteText, type SiteContent } from "../site-content";
import type { ProductDetail } from "../types";

/** Reassurance near the buy buttons plus WhatsApp share / ask links (plain links: no JS needed). */
export function ProductAssurance({ product, content }: { product: ProductDetail; content: SiteContent }) {
  const physical = product.product_type === "physical";
  const url = `${env.siteUrl}${productHref(product)}`;
  const share = `https://wa.me/?text=${encodeURIComponent(t("catalog.whatsappShareText", { name: product.name, url }))}`;
  const ask = content.whatsapp_number ? `https://wa.me/${content.whatsapp_number}?text=${encodeURIComponent(t("catalog.whatsappAskText", { name: product.name, url }))}` : null;
  const points = [
    ...(physical ? [{ icon: HandHeart, text: siteText(content, "catalog.trustHandmade") }] : []),
    { icon: ShieldCheck, text: siteText(content, "catalog.trustSecure") },
    ...(physical ? [{ icon: Package, text: siteText(content, "catalog.trustPacked") }] : []),
  ].filter((p) => p.text);

  return (
    <div className="mt-6 grid gap-4">
      <ul className="grid gap-2 text-sm text-muted-foreground">
        {points.map(({ icon: Icon, text }) => (
          <li key={text} className="flex items-start gap-2.5"><Icon className="mt-0.5 size-4 shrink-0 text-primary" aria-hidden />{text}</li>
        ))}
      </ul>
      <div className="flex flex-wrap gap-x-6 gap-y-1 text-sm">
        <a href={share} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center gap-2 underline-offset-4 hover:text-primary hover:underline">
          <Share2 className="size-4" aria-hidden />{t("catalog.whatsappShare")}<span className="sr-only"> {t("common.opensNewTab")}</span>
        </a>
        {ask ? (
          <a href={ask} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-11 items-center gap-2 underline-offset-4 hover:text-primary hover:underline">
            <MessageCircle className="size-4" aria-hidden />{t("catalog.whatsappAsk")}<span className="sr-only"> {t("common.opensNewTab")}</span>
          </a>
        ) : null}
      </div>
    </div>
  );
}
