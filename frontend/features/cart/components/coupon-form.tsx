"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ApiError } from "@/services/api-client";
import { useCartMutations } from "../cart-context";
import type { Cart } from "../types";

export function CouponForm({ cart }: { cart: Cart }) {
  const { applyCoupon, removeCoupon } = useCartMutations();
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);

  if (cart.coupon) {
    return (
      <div className="flex items-center justify-between gap-3 border border-dashed border-primary/40 px-3 py-2 text-sm">
        <span>
          <span className="eyebrow text-primary">{cart.coupon.code}</span>
          {cart.coupon.description ? <span className="ml-2 text-muted-foreground">{cart.coupon.description}</span> : null}
        </span>
        <button type="button" className="inline-flex min-h-11 items-center underline underline-offset-4" onClick={() => removeCoupon.mutate()}>Remove</button>
      </div>
    );
  }

  return (
    <form
      className="grid gap-2"
      onSubmit={(e) => {
        e.preventDefault();
        setError(null);
        applyCoupon.mutate(code.trim(), {
          onSuccess: () => setCode(""),
          onError: (err) => setError(err instanceof ApiError ? (err.fieldErrors.coupon?.[0] ?? err.message) : "Could not apply this code."),
        });
      }}
    >
      <label htmlFor="coupon" className="eyebrow">Promo code</label>
      <div className="flex gap-2">
        <Input id="coupon" value={code} onChange={(e) => setCode(e.target.value)} placeholder="e.g. WELCOME10" autoComplete="off" aria-invalid={error ? true : undefined} aria-describedby={error ? "coupon-error" : undefined} />
        <Button type="submit" variant="outline" disabled={!code.trim() || applyCoupon.isPending}>Apply</Button>
      </div>
      {error ? <p id="coupon-error" className="text-sm text-destructive">{error}</p> : null}
      {cart.coupon_error ? <p className="text-sm text-destructive">{cart.coupon_error}</p> : null}
    </form>
  );
}
