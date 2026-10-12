"use client";

import { useQuery } from "@tanstack/react-query";
import { Plus } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { productHref } from "@/features/catalog/catalog";
import { t } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";
import { cartApi } from "../api";
import { useCartMutations } from "../cart-context";
import type { Cart } from "../types";

/** "Pairs well with your bag" — server-picked, never repeats what is already in the bag. */
export function CartSuggestions({ cart, onNavigate }: { cart: Cart; onNavigate?: () => void }) {
  const { add } = useCartMutations();
  const itemsKey = cart.items.map((i) => i.product.uuid).sort().join(",");
  const suggestions = useQuery({ queryKey: ["cart", "suggestions", itemsKey], queryFn: cartApi.suggestions, enabled: cart.items.length > 0, staleTime: 60_000 });
  const products = (suggestions.data ?? []).slice(0, 3);

  // Optional extra: stays silent while loading or on error rather than shifting the bag around.
  if (!products.length) return null;

  return (
    <section aria-labelledby="pairs-heading" className="grid gap-3 py-4">
      <h3 id="pairs-heading" className="eyebrow text-muted-foreground">{t("cart.pairsWell")}</h3>
      <ul className="grid gap-3">
        {products.map((product) => (
          <li key={product.uuid} className="flex items-center gap-3">
            <div className="relative aspect-4/5 w-12 shrink-0 overflow-hidden bg-muted">
              {product.images[0] ? <Image src={product.images[0].url} alt="" fill sizes="48px" className="object-cover" /> : null}
            </div>
            <div className="min-w-0 flex-1 text-sm">
              <Link href={productHref(product)} onClick={onNavigate} className="line-clamp-1 font-medium hover:text-primary">{product.name}</Link>
              <p className="text-muted-foreground">{formatMoney(product.price)}</p>
            </div>
            {product.quick_add_variant ? (
              <button
                type="button"
                className="inline-flex size-11 shrink-0 items-center justify-center border border-border hover:border-foreground disabled:opacity-50"
                disabled={add.isPending}
                onClick={() => add.mutate({ variantUuid: product.quick_add_variant!, quantity: 1 })}
                aria-label={t("cart.quickAdd", { name: product.name })}
              >
                <Plus className="size-4" aria-hidden />
              </button>
            ) : null}
          </li>
        ))}
      </ul>
    </section>
  );
}
