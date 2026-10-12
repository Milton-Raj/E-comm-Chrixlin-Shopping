"use client";

import { Gift } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { t } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";
import { useCartMutations } from "../cart-context";
import type { Cart } from "../types";

/** Paid gift wrapping with an optional message; the charge comes from the server's priced cart. */
export function GiftOption({ cart }: { cart: Cart }) {
  const { gift } = useCartMutations();
  const [message, setMessage] = useState(cart.gift.message ?? "");
  if (!cart.gift.available) return null;

  const saved = (cart.gift.message ?? "") === message.trim();
  const label = cart.gift.price.amount > 0 ? t("cart.giftWrapOffer", { price: formatMoney(cart.gift.price) }) : t("cart.giftWrapFree");

  return (
    <div className="grid gap-3 border border-border p-3">
      <label className="flex min-h-11 cursor-pointer items-start gap-3 text-sm">
        <input type="checkbox" className="mt-0.5 size-4 accent-primary" checked={cart.gift.wrap} disabled={gift.isPending}
          onChange={(e) => gift.mutate({ wrap: e.target.checked, message: e.target.checked ? message.trim() || null : null })} />
        <span className="grid gap-0.5">
          <span className="flex items-center gap-2 font-medium"><Gift className="size-4 text-primary" aria-hidden />{label}</span>
          <span className="text-xs text-muted-foreground">{t("cart.giftWrapHint")}</span>
        </span>
      </label>
      {cart.gift.wrap ? (
        <form className="grid gap-2" onSubmit={(e) => { e.preventDefault(); gift.mutate({ wrap: true, message: message.trim() || null }); }}>
          <label htmlFor="gift-message" className="text-sm">{t("cart.giftMessage")}</label>
          <Textarea id="gift-message" maxLength={300} className="min-h-20" value={message} onChange={(e) => setMessage(e.target.value)} />
          <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
            <span aria-live="polite">{saved && message.trim() ? t("cart.giftMessageSaved") : `${message.length}/300`}</span>
            <Button type="submit" size="sm" variant="outline" disabled={saved || gift.isPending}>{t("cart.giftMessageSave")}</Button>
          </div>
        </form>
      ) : null}
    </div>
  );
}
