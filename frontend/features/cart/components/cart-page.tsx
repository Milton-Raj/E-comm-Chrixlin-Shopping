"use client";

import { ShoppingBag } from "lucide-react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { useCart } from "../cart-context";
import { CartLines } from "./cart-lines";
import { CartTotals } from "./cart-totals";
import { CouponForm } from "./coupon-form";

export function CartPage() {
  const { data: cart, isPending, isError, refetch } = useCart();

  if (isPending) return <LoadingState lines={5} />;
  if (isError) return <ErrorState onRetry={() => void refetch()} />;
  if (!cart.items.length) {
    return (
      <EmptyState
        icon={<ShoppingBag />}
        title="Your bag is empty"
        description="Products you add to your bag will appear here."
        action={<ButtonLink href="/shop">Continue shopping</ButtonLink>}
      />
    );
  }

  return (
    <div className="grid gap-10 lg:grid-cols-3 lg:gap-14">
      <div className="lg:col-span-2">
        {cart.warnings.length ? (
          <ul className="mb-4 grid gap-2" role="status">
            {cart.warnings.map((w) => <li key={w.item + w.code} className="border-l-2 border-primary bg-muted/60 px-3 py-2 text-sm">{w.message}</li>)}
          </ul>
        ) : null}
        <div className="border-t border-border">
          <CartLines cart={cart} />
        </div>
      </div>
      <aside className="grid content-start gap-6 border border-border bg-card p-6 lg:sticky lg:top-36">
        <h2 className="text-xl font-semibold">Order summary</h2>
        <CartTotals cart={cart} />
        <CouponForm cart={cart} />
        <ButtonLink href="/checkout" size="lg">Proceed to checkout</ButtonLink>
        <p className="text-center text-xs text-muted-foreground">Secure checkout · UPI, cards & net banking</p>
      </aside>
    </div>
  );
}
