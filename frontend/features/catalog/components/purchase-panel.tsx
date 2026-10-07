"use client";

import { ArrowRight, Minus, Plus, ShoppingBag } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { useCartMutations } from "@/features/cart/cart-context";
import { t } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { findVariant } from "../catalog";
import type { ProductDetail } from "../types";
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
  const isDigital = product.product_type === "digital";
  const variant = findVariant(product.variants, product.options, selected);
  const purchasable = Boolean(variant?.available);

  const isValueAvailable = (optionName: string, value: string) =>
    product.variants.some((v) => v.available && v.options[optionName] === value && product.options.every((o) => o.name === optionName || v.options[o.name] === selected[o.name]));

  const submit = (goToCheckout: boolean) => {
    if (!variant) return;
    add.mutate(
      { variantUuid: variant.uuid, quantity: isDigital ? 1 : quantity, openDrawer: !goToCheckout },
      { onSuccess: () => goToCheckout && router.push("/checkout") },
    );
  };

  return (
    <div className="grid gap-6">
      {variant && product.variants.length > 1 ? (
        <p className="text-sm text-muted-foreground" aria-live="polite">
          {formatMoney(variant.price)}
          {variant.compare_at_price ? <span className="ml-2 line-through">{formatMoney(variant.compare_at_price)}</span> : null}
        </p>
      ) : null}

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

      <div className="grid gap-3 sm:grid-cols-2">
        <Button size="lg" className="h-14 text-sm" disabled={!purchasable || add.isPending} onClick={() => submit(false)}>
          <ShoppingBag className="size-4.5" aria-hidden />
          {purchasable ? t("catalog.addToBag") : t("catalog.outOfStock")}
        </Button>
        <Button size="lg" variant="dark" className="group h-14 text-sm shadow-md shadow-brand-black/20 hover:shadow-lg hover:shadow-primary/30" disabled={!purchasable || add.isPending} onClick={() => submit(true)}>
          {t("catalog.buyNow")}
          <ArrowRight className="size-4.5 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden />
        </Button>
        <WishlistButton productUuid={product.uuid} name={product.name} withLabel className="sm:col-span-2" />
      </div>
    </div>
  );
}
