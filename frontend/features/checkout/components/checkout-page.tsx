"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Lock, ShieldCheck } from "lucide-react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useMemo, useState, type FormEvent, type ReactNode } from "react";
import { toast } from "sonner";
import { FormMessage, TextField } from "@/components/form-field";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { useCurrentUser } from "@/features/auth/hooks";
import { checkoutApi } from "@/features/cart/api";
import { cartKey } from "@/features/cart/cart-context";
import { CartTotals } from "@/features/cart/components/cart-totals";
import { CouponForm } from "@/features/cart/components/coupon-form";
import type { Address, Cart, Gateway, PlacedOrder } from "@/features/cart/types";
import { formatMoney } from "@/lib/money";
import { countries, indianStates } from "@/lib/regions";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { openRazorpay } from "../razorpay";

type Step = "contact" | "address" | "shipping" | "payment";

const checkoutKey = ["checkout"] as const;

function message(error: unknown): string {
  if (error instanceof ApiError) return Object.values(error.fieldErrors)[0]?.[0] ?? error.message;
  return error instanceof Error ? error.message : "Something went wrong. Please try again.";
}

/** Contact → Address → Shipping → Payment (PRD §26, §61). All totals come from the server. */
export function CheckoutPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { data: user } = useCurrentUser();
  const checkout = useQuery({ queryKey: checkoutKey, queryFn: checkoutApi.get });
  const [step, setStep] = useState<Step>("contact");
  const [placed, setPlaced] = useState<PlacedOrder | null>(null);
  const [idempotencyKey] = useState(() => crypto.randomUUID());

  const store = (cart: Cart) => {
    queryClient.setQueryData(checkoutKey, (old: { cart: Cart; gateways: Gateway[] } | undefined) => (old ? { ...old, cart } : old));
    queryClient.setQueryData(cartKey, cart);
  };

  const cart = checkout.data?.cart;
  const steps: Step[] = useMemo(() => (cart?.requires_shipping === false ? ["contact", "payment"] : ["contact", "address", "shipping", "payment"]), [cart?.requires_shipping]);

  if (checkout.isPending) return <LoadingState lines={8} />;
  if (checkout.isError) return <ErrorState onRetry={() => void checkout.refetch()} />;
  if (!cart || !cart.items.length) {
    if (placed) return <LoadingState lines={4} />;
    return <EmptyState title="Your bag is empty" description="Add something you love, then come back to check out." action={<ButtonLink href="/shop">Continue shopping</ButtonLink>} />;
  }

  const next = (current: Step) => setStep(steps[Math.min(steps.indexOf(current) + 1, steps.length - 1)]!);
  const done = (s: Step) => steps.indexOf(s) < steps.indexOf(step);

  const finish = (order: PlacedOrder) => {
    queryClient.removeQueries({ queryKey: cartKey });
    queryClient.removeQueries({ queryKey: checkoutKey });
    const token = order.access_token ? `?token=${encodeURIComponent(order.access_token)}` : "";
    router.push(`/checkout/confirmation/${order.order_number}${token}`);
  };

  return (
    <div className="grid gap-10 lg:grid-cols-5 lg:gap-14">
      <ol className="grid content-start gap-4 lg:col-span-3">
        <StepCard n={1} title="Contact" active={step === "contact"} done={done("contact")} summary={cart.contact.email ?? undefined} onEdit={() => setStep("contact")}>
          <ContactStep cart={cart} defaultEmail={user?.email ?? ""} onSaved={(c) => { store(c); next("contact"); }} />
        </StepCard>
        {cart.requires_shipping ? (
          <>
            <StepCard n={2} title="Delivery address" active={step === "address"} done={done("address")} summary={cart.shipping_address ? `${cart.shipping_address.name}, ${cart.shipping_address.city} ${cart.shipping_address.postal_code}` : undefined} onEdit={() => setStep("address")}>
              <AddressStep cart={cart} defaultName={user?.name ?? ""} onSaved={(c) => { store(c); next("address"); }} />
            </StepCard>
            <StepCard n={3} title="Delivery method" active={step === "shipping"} done={done("shipping")} summary={cart.shipping_method?.name} onEdit={() => setStep("shipping")}>
              <ShippingStep cart={cart} onSaved={(c) => { store(c); next("shipping"); }} />
            </StepCard>
          </>
        ) : null}
        <StepCard n={steps.length} title="Payment" active={step === "payment"} done={false}>
          <PaymentStep cart={cart} gateways={checkout.data.gateways} placed={placed} idempotencyKey={idempotencyKey} onPlaced={setPlaced} onPaid={finish} onCartChanged={() => void checkout.refetch()} />
        </StepCard>
      </ol>

      <aside className="grid content-start gap-6 border border-border bg-card p-6 lg:sticky lg:top-36 lg:col-span-2">
        <h2 className="text-xl font-semibold">Order summary</h2>
        <ul className="grid gap-4">
          {cart.items.map((item) => (
            <li key={item.uuid} className="flex gap-3">
              <div className="relative aspect-4/5 w-16 shrink-0 overflow-hidden bg-muted">
                {item.product.image ? <Image src={item.product.image.url} alt={item.product.image.alt} fill sizes="64px" className="object-cover" /> : null}
                <span className="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-brand-black text-xs text-brand-cultured">{item.quantity}</span>
              </div>
              <div className="min-w-0 flex-1 text-sm">
                <p className="font-medium">{item.product.name}</p>
                {item.variant.name ? <p className="text-muted-foreground">{item.variant.name}</p> : null}
              </div>
              <p className="text-sm">{formatMoney(item.line_total)}</p>
            </li>
          ))}
        </ul>
        {!placed ? <CouponForm cart={cart} /> : null}
        <CartTotals cart={cart} />
        <p className="flex items-center gap-2 text-xs text-muted-foreground"><Lock className="size-3.5" aria-hidden /> Payments are verified securely on our servers.</p>
      </aside>
    </div>
  );
}

function StepCard({ n, title, active, done, summary, onEdit, children }: { n: number; title: string; active: boolean; done: boolean; summary?: string; onEdit?: () => void; children: ReactNode }) {
  return (
    <li className={cn("border border-border bg-card p-5 md:p-6", active && "border-foreground")}>
      <div className="flex items-center justify-between gap-3">
        <h2 className="flex items-center gap-3 text-lg font-semibold">
          <span className={cn("flex size-7 items-center justify-center rounded-full border text-sm font-sans", done ? "border-primary bg-primary text-primary-foreground" : "border-foreground")}>
            {done ? <Check className="size-4" aria-hidden /> : n}
          </span>
          {title}
        </h2>
        {done && onEdit ? <button type="button" className="eyebrow inline-flex min-h-11 items-center underline underline-offset-4" onClick={onEdit}>Edit</button> : null}
      </div>
      {done && summary ? <p className="mt-2 pl-10 text-sm text-muted-foreground">{summary}</p> : null}
      {active ? <div className="mt-6">{children}</div> : null}
    </li>
  );
}

function ContactStep({ cart, defaultEmail, onSaved }: { cart: Cart; defaultEmail: string; onSaved: (cart: Cart) => void }) {
  // Typed value wins; otherwise fall back to the saved contact or the signed-in account (which may load later).
  const [typedEmail, setEmail] = useState<string | null>(null);
  const email = typedEmail ?? cart.contact.email ?? defaultEmail;
  const [phone, setPhone] = useState(cart.contact.phone ?? "");
  const save = useMutation({ mutationFn: () => checkoutApi.contact(email.trim(), phone.trim() || null), onSuccess: onSaved });

  return (
    <form className="grid gap-4" onSubmit={(e: FormEvent) => { e.preventDefault(); save.mutate(); }}>
      <FormMessage message={save.isError ? message(save.error) : null} />
      <TextField id="email" type="email" autoComplete="email" label="Email" required value={email} onChange={(e) => setEmail(e.target.value)} hint="Your receipt and download links are sent here." />
      <TextField id="phone" type="tel" autoComplete="tel" label="Phone (for delivery updates)" value={phone} onChange={(e) => setPhone(e.target.value)} />
      <div><Button type="submit" size="lg" disabled={save.isPending || !email}>Continue</Button></div>
    </form>
  );
}

function AddressStep({ cart, defaultName, onSaved }: { cart: Cart; defaultName: string; onSaved: (cart: Cart) => void }) {
  const [address, setAddress] = useState<Address>(cart.shipping_address ?? { name: defaultName, phone: cart.contact.phone, line1: "", line2: "", city: "", state_code: "TN", postal_code: "", country_code: "IN" });
  const save = useMutation({ mutationFn: () => checkoutApi.address(address), onSuccess: onSaved });
  const fieldError = (name: string) => (save.error instanceof ApiError ? save.error.fieldErrors[name]?.[0] : undefined);
  const set = (key: keyof Address) => (e: { target: { value: string } }) => setAddress((a) => ({ ...a, [key]: e.target.value }));
  const india = address.country_code === "IN";

  return (
    <form className="grid gap-4 md:grid-cols-2" onSubmit={(e: FormEvent) => { e.preventDefault(); save.mutate(); }}>
      <div className="md:col-span-2"><FormMessage message={save.isError && !(save.error instanceof ApiError && save.error.isValidation) ? message(save.error) : null} /></div>
      <div className="md:col-span-2"><TextField id="name" autoComplete="name" label="Full name" required value={address.name} onChange={set("name")} error={fieldError("name")} /></div>
      <div className="md:col-span-2"><TextField id="line1" autoComplete="address-line1" label="Address" required value={address.line1} onChange={set("line1")} error={fieldError("line1")} /></div>
      <div className="md:col-span-2"><TextField id="line2" autoComplete="address-line2" label="Apartment, landmark (optional)" value={address.line2 ?? ""} onChange={set("line2")} /></div>
      <TextField id="city" autoComplete="address-level2" label="City" required value={address.city} onChange={set("city")} error={fieldError("city")} />
      <TextField id="postal_code" autoComplete="postal-code" inputMode={india ? "numeric" : "text"} label={india ? "PIN code" : "Postal code"} required value={address.postal_code} onChange={set("postal_code")} error={fieldError("postal_code")} />
      <div className="grid gap-2">
        <Label htmlFor="country_code">Country</Label>
        <select id="country_code" autoComplete="country" className="min-h-11 border border-input bg-transparent px-3 text-sm" value={address.country_code} onChange={(e) => setAddress((a) => ({ ...a, country_code: e.target.value, state_code: e.target.value === "IN" ? "TN" : "" }))}>
          {countries.map((c) => <option key={c.code} value={c.code}>{c.name}</option>)}
        </select>
      </div>
      {india ? (
        <div className="grid gap-2">
          <Label htmlFor="state_code">State</Label>
          <select id="state_code" autoComplete="address-level1" className="min-h-11 border border-input bg-transparent px-3 text-sm" value={address.state_code} onChange={set("state_code")}>
            {indianStates.map((s) => <option key={s.code} value={s.code}>{s.name}</option>)}
          </select>
        </div>
      ) : (
        <TextField id="state_code" autoComplete="address-level1" label="State / region code" required value={address.state_code} onChange={set("state_code")} error={fieldError("state_code")} />
      )}
      <div className="md:col-span-2"><TextField id="address_phone" type="tel" autoComplete="tel" label="Phone for the courier" value={address.phone ?? ""} onChange={set("phone")} /></div>
      <div className="md:col-span-2"><Button type="submit" size="lg" disabled={save.isPending}>Continue</Button></div>
    </form>
  );
}

function ShippingStep({ cart, onSaved }: { cart: Cart; onSaved: (cart: Cart) => void }) {
  const [code, setCode] = useState(cart.shipping_method?.code ?? cart.shipping_options[0]?.code ?? "standard");
  const save = useMutation({ mutationFn: () => checkoutApi.shippingMethod(code), onSuccess: onSaved });

  return (
    <form className="grid gap-4" onSubmit={(e: FormEvent) => { e.preventDefault(); save.mutate(); }}>
      <fieldset className="grid gap-3">
        <legend className="sr-only">Delivery method</legend>
        {cart.shipping_options.map((option) => (
          <label key={option.code} className={cn("flex min-h-14 cursor-pointer items-center gap-4 border px-4 py-3", code === option.code ? "border-foreground" : "border-border")}>
            <input type="radio" name="shipping" value={option.code} checked={code === option.code} onChange={() => setCode(option.code)} className="size-4 accent-primary" />
            <span className="flex-1">
              <span className="block font-medium">{option.name}</span>
              <span className="block text-sm text-muted-foreground">{option.description}</span>
            </span>
            <span className="text-sm font-medium">{option.amount.amount === 0 ? "Complimentary" : formatMoney(option.amount)}</span>
          </label>
        ))}
      </fieldset>
      <div><Button type="submit" size="lg" disabled={save.isPending}>Continue to payment</Button></div>
    </form>
  );
}

function PaymentStep({ cart, gateways, placed, idempotencyKey, onPlaced, onPaid, onCartChanged }: {
  cart: Cart; gateways: Gateway[]; placed: PlacedOrder | null; idempotencyKey: string;
  onPlaced: (order: PlacedOrder) => void; onPaid: (order: PlacedOrder) => void; onCartChanged: () => void;
}) {
  const [gateway, setGateway] = useState(gateways[0]?.key ?? "");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const confirm = async (order: PlacedOrder, data: Record<string, unknown>) => {
    const result = await checkoutApi.confirmPayment(order.order_number, data, order.access_token);
    if (result.payment_status === "captured") {
      toast.success("Payment confirmed. Thank you!");
      onPaid(order);
    } else {
      setError("The payment did not go through. You can try again.");
    }
  };

  const placeAndPay = async () => {
    setError(null);
    setBusy(true);
    try {
      const order = placed ?? (await checkoutApi.placeOrder(gateway, cart.totals.total.amount, idempotencyKey));
      onPlaced(order);
      if (order.payment.gateway === "razorpay") {
        const result = await openRazorpay(order.payment.client_payload);
        if (result) await confirm(order, result);
        else setError("Payment was cancelled. You can try again.");
      }
    } catch (e) {
      if (e instanceof ApiError && (e.code === "price_changed" || e.code === "cart_changed")) onCartChanged();
      setError(message(e));
    } finally {
      setBusy(false);
    }
  };

  const testPay = async (outcome: "success" | "failure") => {
    if (!placed) return;
    setBusy(true);
    setError(null);
    try {
      if (outcome === "failure") {
        await checkoutApi.confirmPayment(placed.order_number, { outcome }, placed.access_token);
        setError("The test payment was declined. Try again, or choose another method.");
      } else {
        await confirm(placed, { outcome });
      }
    } catch (e) {
      setError(message(e));
    } finally {
      setBusy(false);
    }
  };

  const retry = async () => {
    if (!placed) return;
    setBusy(true);
    setError(null);
    try {
      const fresh = await checkoutApi.retryPayment(placed.order_number, placed.payment.gateway, placed.access_token);
      const order = { ...placed, payment: fresh };
      onPlaced(order);
      if (fresh.gateway === "razorpay") {
        const result = await openRazorpay(fresh.client_payload);
        if (result) await confirm(order, result);
      }
    } catch (e) {
      setError(message(e));
    } finally {
      setBusy(false);
    }
  };

  if (!gateways.length) {
    return <FormMessage message="Online payment is not configured yet. Please contact us to complete your order." />;
  }

  return (
    <div className="grid gap-5">
      <FormMessage message={error} />
      {!placed ? (
        <>
          <fieldset className="grid gap-3">
            <legend className="sr-only">Payment method</legend>
            {gateways.map((g) => (
              <label key={g.key} className={cn("flex min-h-14 cursor-pointer items-center gap-4 border px-4 py-3", gateway === g.key ? "border-foreground" : "border-border")}>
                <input type="radio" name="gateway" value={g.key} checked={gateway === g.key} onChange={() => setGateway(g.key)} className="size-4 accent-primary" />
                <span>
                  <span className="block font-medium">{g.name}</span>
                  <span className="block text-sm text-muted-foreground">{g.description}</span>
                </span>
              </label>
            ))}
          </fieldset>
          <Button size="lg" onClick={() => void placeAndPay()} disabled={busy || !gateway}>
            {busy ? "Please wait…" : `Pay ${formatMoney(cart.totals.total)}`}
          </Button>
          <p className="flex items-center gap-2 text-xs text-muted-foreground"><ShieldCheck className="size-4" aria-hidden /> By placing your order you agree to our terms and refund policy.</p>
        </>
      ) : placed.payment.gateway === "test" ? (
        <div className="grid gap-4 border border-dashed border-primary/50 bg-muted/40 p-5">
          <p className="eyebrow text-primary">Test payment mode</p>
          <p className="text-sm text-muted-foreground">
            Order <strong className="text-foreground">{placed.order_number}</strong> is reserved for 30 minutes. No real money moves in test mode — the server confirms the payment exactly as a real gateway webhook would.
          </p>
          <div className="grid gap-3 sm:grid-cols-2">
            <Button size="lg" onClick={() => void testPay("success")} disabled={busy}>Pay {formatMoney(cart.totals.total)}</Button>
            <Button size="lg" variant="outline" onClick={() => void testPay("failure")} disabled={busy}>Simulate failure</Button>
          </div>
        </div>
      ) : (
        <div className="grid gap-3">
          <p className="text-sm text-muted-foreground">Order <strong className="text-foreground">{placed.order_number}</strong> is awaiting payment.</p>
          <Button size="lg" onClick={() => void retry()} disabled={busy}>Try payment again</Button>
        </div>
      )}
    </div>
  );
}
