"use client";

import { Minus, Plus, Trash2, X } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { productHref } from "@/features/catalog/catalog";
import { formatMoney } from "@/lib/money";
import { cn } from "@/lib/utils";
import { useCartMutations } from "../cart-context";
import type { Cart } from "../types";

/** Line items with quantity controls; used by the drawer and the bag page. */
export function CartLines({ cart, compact = false }: { cart: Cart; compact?: boolean }) {
  const { update, remove } = useCartMutations();
  const busy = update.isPending || remove.isPending;

  return (
    <ul className="divide-y divide-border">
      {cart.items.map((item) => (
        <li key={item.uuid} className={cn("flex gap-4 py-5", !item.available && "opacity-60")}>
          <Link href={productHref(item.product)} className="relative aspect-4/5 w-20 shrink-0 overflow-hidden bg-muted md:w-24">
            {item.product.image ? <Image src={item.product.image.url} alt={item.product.image.alt} fill sizes="96px" className="object-cover" /> : null}
          </Link>
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                {item.product.brand ? <p className="eyebrow text-muted-foreground">{item.product.brand}</p> : null}
                <Link href={productHref(item.product)} className="text-base leading-snug font-medium hover:text-primary">
                  {item.product.name}
                </Link>
                {item.variant.name ? <p className="text-sm text-muted-foreground">{item.variant.name}</p> : null}
                {!item.available ? <p className="text-sm text-destructive">No longer available</p> : null}
              </div>
              <button
                type="button"
                onClick={() => remove.mutate(item.uuid)}
                disabled={busy}
                aria-label={`Remove ${item.product.name}`}
                className="inline-flex size-11 shrink-0 items-center justify-center text-muted-foreground hover:text-foreground"
              >
                <X className="size-4" aria-hidden />
              </button>
            </div>
            <div className="mt-auto flex items-center justify-between gap-3">
              {item.product.product_type === "physical" ? (
                <div className="inline-flex items-center border border-border" role="group" aria-label={`Quantity for ${item.product.name}`}>
                  {/* At 1, minus takes the piece out of the bag (same as the ✕), so quantity can reach zero. */}
                  <button type="button" className="inline-flex size-10 items-center justify-center hover:bg-muted disabled:opacity-40" disabled={busy}
                    onClick={() => (item.quantity <= 1 ? remove.mutate(item.uuid) : update.mutate({ item: item.uuid, quantity: item.quantity - 1 }))}
                    aria-label={item.quantity <= 1 ? `Remove ${item.product.name}` : "Decrease quantity"}>
                    {item.quantity <= 1 ? <Trash2 className="size-3.5" aria-hidden /> : <Minus className="size-3.5" aria-hidden />}
                  </button>
                  <output className="w-8 text-center text-sm">{item.quantity}</output>
                  <button type="button" className="inline-flex size-10 items-center justify-center hover:bg-muted disabled:opacity-40" disabled={busy || item.quantity >= item.max_quantity} onClick={() => update.mutate({ item: item.uuid, quantity: item.quantity + 1 })} aria-label="Increase quantity">
                    <Plus className="size-3.5" aria-hidden />
                  </button>
                </div>
              ) : (
                <span className="eyebrow text-muted-foreground">Digital · instant download</span>
              )}
              <p className={cn("shrink-0 font-medium whitespace-nowrap", compact ? "text-sm" : "")}>{formatMoney(item.line_total)}</p>
            </div>
          </div>
        </li>
      ))}
    </ul>
  );
}
