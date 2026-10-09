import { formatMoney } from "@/lib/money";
import type { Cart } from "../types";

export function CartTotals({ cart, showShipping = true }: { cart: Cart; showShipping?: boolean }) {
  const { totals } = cart;
  return (
    <div className="grid gap-2 text-sm">
    <dl className="grid gap-2">
      <Row label="Subtotal" value={formatMoney(totals.subtotal)} />
      {totals.discount.amount > 0 ? <Row label={cart.coupon ? `Discount (${cart.coupon.code})` : "Discount"} value={`− ${formatMoney(totals.discount)}`} className="text-primary" /> : null}
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
