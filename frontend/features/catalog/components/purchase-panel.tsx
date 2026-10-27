"use client";

import { ArrowRight, Minus, Plus, ShoppingBag } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState, useSyncExternalStore } from "react";
import { createPortal } from "react-dom";
import { Button } from "@/components/ui/button";
import { useCartMutations } from "@/features/cart/cart-context";
import { t } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { findVariant, savings } from "../catalog";
import type { ProductDetail } from "../types";
import { NotifyMe } from "./notify-me";
import { WishlistButton } from "./wishlist-button";

/** Variant selection, quantity and purchase CTAs (PRD §58). Prices shown come from the API. */
export function PurchasePanel({ product }: { product: ProductDetail }) {
  const router = useRouter();
  const { add } = useCartMutations();
  const [selected, setSelected] = useState<Record<string, string>>(() => {
    const first = product.variants.find((v) => v.available) ?? product.variants[0];
    return Object.fromEntries(product.options.map((o) => [o.name, first?.options[o.name] ?? o.values[0] ?? ""]));
  });
  const [quantity, setQuantity] = useState(1);
  // Server render and desktop: buttons inline. Phones/tablets after hydration: only the pinned bar.
  const pinned = useSyncExternalStore(subscribeDesktop, () => !desktopQuery().matches, () => false);
  const isDigital = product.product_type === "digital";
  const variant = findVariant(product.variants, product.options, selected);
  const purchasable = Boolean(variant?.available);
  const variantSaving = variant ? savings(variant) : null;
  // The heading already shows the product price; repeat it here only when the chosen variant costs something else.
  const variantPriceDiffers = Boolean(variant && (variant.price.amount !== product.price.amount || (variant.compare_at_price?.amount ?? null) !== (product.compare_at_price?.amount ?? null)));

  const isValueAvailable = (optionName: string, value: string) =>
    product.variants.some((v) => v.available && v.options[optionName] === value && product.options.every((o) => o.name === optionName || v.options[o.name] === selected[o.name]));

  const submit = (goToCheckout: boolean) => {
    if (!variant) return;
    add.mutate(
      { variantUuid: variant.uuid, quantity: isDigital ? 1 : quantity, openDrawer: !goToCheckout },
      { onSuccess: () => goToCheckout && router.push("/checkout") },
    );
  };

  const actions = (
    <>
      <Button size="lg" className="h-12 min-w-0 flex-1 px-4 text-sm" disabled={!purchasable || add.isPending} onClick={() => submit(false)}>
        <ShoppingBag className="hidden size-4.5 sm:block" aria-hidden />
        {purchasable ? t("catalog.addToBag") : t("catalog.outOfStock")}
      </Button>
      <Button size="lg" variant="dark" className="group h-12 min-w-0 flex-1 px-4 text-sm shadow-md shadow-brand-black/20 hover:shadow-lg hover:shadow-primary/30" disabled={!purchasable || add.isPending} onClick={() => submit(true)}>
        {t("catalog.buyNow")}
        <ArrowRight className="size-4.5 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden />
      </Button>
      <WishlistButton productUuid={product.uuid} name={product.name} className="size-12 shrink-0 rounded-none border border-border bg-background backdrop-blur-none hover:border-foreground hover:bg-background" />
    </>
  );

  return (
    <div className="grid gap-5">
      {variant && variantPriceDiffers ? (
        <p className="text-sm text-muted-foreground" aria-live="polite">
          {formatMoney(variant.price)}
          {variantSaving && variant.compare_at_price ? (
            <>
              <span className="ml-2 line-through"><span className="sr-only">{t("catalog.wasPrice", { price: "" })}</span>{formatMoney(variant.compare_at_price)}</span>
              <span className="ml-2 font-medium text-primary">{t("catalog.youSave", { amount: formatMoney(variantSaving) })}</span>
            </>
          ) : null}
        </p>
      ) : null}

      {/* Options and quantity share a row on wider screens to keep the buy buttons above the fold. */}
      <div className="flex flex-wrap items-end gap-x-10 gap-y-5">
        {product.options.map((option) => (
          <fieldset key={option.name}>
            <legend className="eyebrow mb-3">
              {option.name}: <span className="text-muted-foreground">{selected[option.name]}</span>
            </legend>
            <div className="flex flex-wrap gap-2">
              {option.values.map((value) => {
                const active = selected[option.name] === value;
                const available = isValueAvailable(option.name, value);
                return (
                  <button
                    key={value}
                    type="button"
                    aria-pressed={active}
                    onClick={() => setSelected((s) => ({ ...s, [option.name]: value }))}
                    className={cn(
                      "min-h-11 min-w-11 border px-4 text-sm transition-colors",
                      active ? "border-foreground bg-foreground text-background" : "border-border hover:border-foreground",
                      !available && "text-muted-foreground line-through",
                    )}
                  >
                    {value}
                  </button>
                );
              })}
            </div>
          </fieldset>
        ))}

        {!isDigital ? (
          <div>
            <p className="eyebrow mb-3" id="qty-label">{t("catalog.quantity")}</p>
            <div className="inline-flex items-center border border-border" role="group" aria-labelledby="qty-label">
              <button type="button" className="inline-flex size-11 items-center justify-center hover:bg-muted" onClick={() => setQuantity((q) => Math.max(1, q - 1))} aria-label={t("catalog.decrease")}>
                <Minus className="size-4" aria-hidden />
              </button>
              <output className="w-10 text-center text-sm" aria-live="polite">{quantity}</output>
              <button type="button" className="inline-flex size-11 items-center justify-center hover:bg-muted" onClick={() => setQuantity((q) => Math.min(10, q + 1))} aria-label={t("catalog.increase")}>
                <Plus className="size-4" aria-hidden />
              </button>
            </div>
          </div>
        ) : null}
      </div>

      {variant && !purchasable ? <NotifyMe key={variant.uuid} slug={product.slug} variantUuid={variant.uuid} /> : null}

      {/* Desktop: one inline row above the fold. Phones/tablets: once hydrated, the same buttons move to a bar
          always pinned above the bottom navigation, portalled to <body> (the page-transition wrapper's transform
          would otherwise pin a fixed element to the page, not the screen). A matching spacer after the footer
          keeps the bar from ever covering the end of the page. */}
      {pinned
        ? createPortal(
            <>
              <div className="h-18 lg:hidden" aria-hidden />
              <div className="fixed inset-x-0 above-bottom-nav z-30 flex gap-2 border-t border-border bg-background/95 px-4 py-3 backdrop-blur md:px-6 lg:hidden">{actions}</div>
            </>,
            document.body,
          )
        : <div className="flex gap-3">{actions}</div>}
    </div>
  );
}

const desktopQuery = () => window.matchMedia("(min-width: 64rem)"); // Tailwind lg
const subscribeDesktop = (onChange: () => void) => {
  const query = desktopQuery();
  query.addEventListener("change", onChange);
  return () => query.removeEventListener("change", onChange);
};
