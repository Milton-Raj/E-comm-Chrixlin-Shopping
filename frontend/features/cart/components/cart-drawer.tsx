"use client";

import { ShoppingBag } from "lucide-react";
import { ButtonLink } from "@/components/button-link";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { useCart, useCartDrawer } from "../cart-context";
import { CartLines } from "./cart-lines";
import { CartTotals } from "./cart-totals";

/** Opens after "Add to bag" (PRD §60). */
export function CartDrawer() {
  const { drawerOpen, setDrawerOpen } = useCartDrawer();
  const { data: cart } = useCart();
  const close = () => setDrawerOpen(false);

  return (
    <Sheet open={drawerOpen} onOpenChange={setDrawerOpen}>
      <SheetContent side="right" className="flex flex-col gap-0 bg-background p-0 data-[side=right]:w-full data-[side=right]:sm:max-w-md">
        <SheetHeader className="border-b border-border p-6">
          <SheetTitle className="text-xl font-semibold">Your bag</SheetTitle>
          <SheetDescription>{cart?.item_count ? `${cart.item_count} ${cart.item_count === 1 ? "item" : "items"}` : "Your bag is empty"}</SheetDescription>
        </SheetHeader>
        {cart && cart.items.length > 0 ? (
          <>
            <div className="flex-1 overflow-y-auto px-6">
              <CartLines cart={cart} compact />
            </div>
            <div className="grid gap-4 border-t border-border p-6">
              <CartTotals cart={cart} showShipping={false} />
              <div className="grid grid-cols-2 gap-3">
                <ButtonLink href="/cart" variant="outline" onClick={close}>View bag</ButtonLink>
                <ButtonLink href="/checkout" onClick={close}>Checkout</ButtonLink>
              </div>
            </div>
          </>
        ) : (
          <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <ShoppingBag className="size-8 text-muted-foreground" aria-hidden />
            <p className="text-muted-foreground">Nothing here yet.</p>
            <ButtonLink href="/shop" onClick={close}>Continue shopping</ButtonLink>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
