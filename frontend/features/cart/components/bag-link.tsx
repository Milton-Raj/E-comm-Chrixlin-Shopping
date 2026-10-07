"use client";

import { ShoppingBag } from "lucide-react";
import { useCart, useCartDrawer } from "../cart-context";

/** Header bag icon with live item count; opens the drawer. */
export function BagButton({ className }: { className?: string }) {
  const { data: cart } = useCart();
  const { setDrawerOpen } = useCartDrawer();
  const count = cart?.item_count ?? 0;

  return (
    <button type="button" onClick={() => setDrawerOpen(true)} className={className} aria-label={count ? `Bag, ${count} items` : "Bag"}>
      <span className="relative">
        <ShoppingBag className="size-5" aria-hidden />
        {count > 0 ? (
          <span className="absolute -top-2 -right-2.5 flex size-5 items-center justify-center rounded-full bg-primary text-xs leading-none font-medium text-primary-foreground" aria-hidden>
            {count > 9 ? "9+" : count}
          </span>
        ) : null}
      </span>
    </button>
  );
}
