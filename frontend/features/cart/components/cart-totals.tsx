import { t } from "@/lib/i18n";
import { formatMoney } from "@/lib/money";
import type { Cart } from "../types";

export function CartTotals({ cart, showShipping = true }: { cart: Cart; showShipping?: boolean }) {
  const { totals } = cart;
  return (
    <div className="grid gap-2 text-sm">
      {showShipping && cart.free_delivery ? <FreeDeliveryProgress progress={cart.free_delivery} /> : null}
    <dl className="grid gap-2">
      <Row label="Subtotal" value={formatMoney(totals.subtotal)} />
      {totals.discount.amount > 0 ? <Row label={cart.coupon ? `Discount (${cart.coupon.code})` : "Discount"} value={`− ${formatMoney(totals.discount)}`} className="text-primary" /> : null}
      {totals.gift_wrap.amount > 0 ? <Row label={t("cart.giftWrap")} value={formatMoney(totals.gift_wrap)} /> : null}
      {showShipping && cart.requires_shipping ? (
        <Row label="Delivery" value={cart.shipping_method ? (totals.shipping.amount === 0 ? "Free" : formatMoney(totals.shipping)) : "Calculated at checkout"} />
      ) : null}
      <div className="mt-2 flex items-baseline justify-between border-t border-border pt-3 text-base">
        <dt className="font-medium">Total</dt>
        <dd className="text-xl font-semibold">{formatMoney(totals.total)}</dd>
      </div>
    </dl>
      {cart.tax_inclusive && totals.tax.amount > 0 ? (
        <p className="text-xs text-muted-foreground">
          Includes {formatMoney(totals.tax)} GST{cart.tax_breakdown.length ? ` (${cart.tax_breakdown.map((t) => `${t.code} ${formatMoney(t.amount)}`).join(", ")})` : ""}
        </p>
      ) : null}
    </div>
  );
}

function Row({ label, value, className }: { label: string; value: string; className?: string }) {
  return (
    <div className={`flex justify-between gap-4 ${className ?? ""}`}>
      <dt className="text-muted-foreground">{label}</dt>
      <dd>{value}</dd>
    </div>
  );
}

/** "Add ₹X more for free delivery" — amounts come from the server's priced cart. */
function FreeDeliveryProgress({ progress }: { progress: NonNullable<Cart["free_delivery"]> }) {
  const { threshold, remaining, qualified } = progress;
  const percent = threshold.amount > 0 ? Math.min(100, Math.round(((threshold.amount - remaining.amount) / threshold.amount) * 100)) : 100;
  const label = qualified ? t("cart.freeDeliveryUnlocked") : t("cart.freeDeliveryRemaining", { amount: formatMoney(remaining) });

  return (
    <div className="mb-2 grid gap-1.5" aria-live="polite">
      <p className={qualified ? "font-medium text-primary" : "text-foreground"}>{label}</p>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-label={label} aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent}>
        <div className="h-full rounded-full bg-primary transition-[width] duration-500 motion-reduce:transition-none" style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}
