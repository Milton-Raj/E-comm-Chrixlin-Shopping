"use client";

import { cn } from "@/lib/utils";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { fromMinorUnits, toMinorUnits } from "@/lib/money-input";
import { formatMoney } from "@/lib/money";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type AdminCoupon } from "../api";
import { ExportButton } from "../components/export-button";
import { AdminPage, checkboxLabelClass, Field, FormActions, inputClass, Panel, RequiredNote, StatusBadge } from "../components/kit/admin-page";

const describe = (c: AdminCoupon) =>
  c.type === "percentage" ? `${c.value / 100}% off${c.max_discount ? ` (max ${formatMoney({ amount: c.max_discount, currency: "INR" })})` : ""}`
    : c.type === "fixed" ? `${formatMoney({ amount: c.value, currency: "INR" })} off` : "Free delivery";

export function CouponsPage() {
  const queryClient = useQueryClient();
  const coupons = useQuery({ queryKey: ["admin", "coupons"], queryFn: miscAdminApi.coupons });
  const [editing, setEditing] = useState<AdminCoupon | "new" | null>(null);
  const remove = useMutation({ mutationFn: miscAdminApi.deleteCoupon, onSuccess: () => { toast.success("Coupon deleted."); void queryClient.invalidateQueries({ queryKey: ["admin", "coupons"] }); } });

  return (
    <AdminPage title="Coupons" description="Discount codes customers enter in the bag or at checkout." actions={<><ExportButton type="coupons" /><Button onClick={() => setEditing("new")}>New coupon</Button></>}>
      {editing ? <CouponForm coupon={editing === "new" ? null : editing} onDone={() => { setEditing(null); void queryClient.invalidateQueries({ queryKey: ["admin", "coupons"] }); }} /> : null}
      {coupons.isPending ? <LoadingState lines={5} /> : coupons.isError ? <ErrorState onRetry={() => void coupons.refetch()} /> : (
        <div className="overflow-x-auto border border-border bg-card">
          <table className="w-full text-sm">
            <thead className="border-b border-border text-left text-xs text-muted-foreground uppercase"><tr><th className="p-3">Code</th><th className="p-3">Discount</th><th className="p-3">Rules</th><th className="p-3">Used</th><th className="p-3">Status</th><th className="p-3" /></tr></thead>
            <tbody className="divide-y divide-border">
              {coupons.data.map((c) => {
                const expired = c.ends_at ? new Date(c.ends_at) < new Date() : false;
                return (
                  <tr key={c.uuid}>
                    <td className="p-3 font-mono font-medium">{c.code}<span className="block font-sans text-xs text-muted-foreground">{c.description}</span></td>
                    <td className="p-3">{describe(c)}</td>
                    <td className="p-3 text-xs text-muted-foreground">
                      {[c.min_order_total ? `min ${formatMoney({ amount: c.min_order_total, currency: "INR" })}` : null, c.first_order_only ? "first order" : null, c.per_customer_limit ? `${c.per_customer_limit}/customer` : null, c.ends_at ? `until ${new Date(c.ends_at).toLocaleDateString("en-IN")}` : null].filter(Boolean).join(" · ") || "—"}
                    </td>
                    <td className="p-3">{c.usage_count}{c.usage_limit ? ` / ${c.usage_limit}` : ""}</td>
                    <td className="p-3"><StatusBadge value={!c.is_active ? "draft" : expired ? "cancelled" : "active"} /></td>
                    <td className="p-3 text-right whitespace-nowrap"><Button size="sm" variant="ghost" onClick={() => setEditing(c)}>Edit</Button><Button size="sm" variant="ghost" onClick={() => remove.mutate(c.uuid)}>Delete</Button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </AdminPage>
  );
}

function CouponForm({ coupon, onDone }: { coupon: AdminCoupon | null; onDone: () => void }) {
  const [f, setF] = useState({
    code: coupon?.code ?? "", description: coupon?.description ?? "", type: coupon?.type ?? "percentage",
    value: coupon ? (coupon.type === "percentage" ? String(coupon.value / 100) : fromMinorUnits(coupon.value)) : "10",
    max_discount: fromMinorUnits(coupon?.max_discount), min_order_total: fromMinorUnits(coupon?.min_order_total),
    first_order_only: coupon?.first_order_only ?? false, usage_limit: coupon?.usage_limit ? String(coupon.usage_limit) : "",
    per_customer_limit: coupon?.per_customer_limit ? String(coupon.per_customer_limit) : "", ends_at: coupon?.ends_at?.slice(0, 10) ?? "", is_active: coupon?.is_active ?? true,
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const set = (k: keyof typeof f, v: string | boolean) => setF((x) => ({ ...x, [k]: v }));
  const save = useMutation({
    mutationFn: () => miscAdminApi.saveCoupon(coupon?.uuid ?? null, {
      code: f.code, description: f.description || null, type: f.type,
      value: f.type === "percentage" ? Math.round(Number(f.value) * 100) : f.type === "fixed" ? toMinorUnits(f.value) : 0,
      max_discount: toMinorUnits(f.max_discount), min_order_total: toMinorUnits(f.min_order_total), first_order_only: f.first_order_only,
      usage_limit: f.usage_limit ? Number(f.usage_limit) : null, per_customer_limit: f.per_customer_limit ? Number(f.per_customer_limit) : null,
      ends_at: f.ends_at ? `${f.ends_at}T23:59:59` : null, is_active: f.is_active,
    }),
    onSuccess: () => { toast.success("Coupon saved."); onDone(); },
    onError: (e) => { if (e instanceof ApiError) setErrors(e.fieldErrors); toast.error(e instanceof ApiError ? e.message : "Could not save."); },
  });

  return (
    <Panel title={coupon ? `Edit ${coupon.code}` : "New coupon"}>
      <form className="grid items-start gap-4 md:grid-cols-3" onSubmit={(e) => { e.preventDefault(); save.mutate(); }}>
        <div className="md:col-span-3"><RequiredNote /></div>
        <Field label="Code" htmlFor="c-code" required error={errors.code?.[0]}><input id="c-code" className={cn(inputClass, "uppercase")} value={f.code} onChange={(e) => set("code", e.target.value.toUpperCase())} required /></Field>
        <Field label="Type" htmlFor="c-type" required><select id="c-type" className={inputClass} value={f.type} onChange={(e) => set("type", e.target.value)}><option value="percentage">Percentage off</option><option value="fixed">Fixed amount off</option><option value="free_shipping">Free delivery</option></select></Field>
        {f.type !== "free_shipping" ? <Field label={f.type === "percentage" ? "Percent off" : "Amount off (₹)"} htmlFor="c-value" required error={errors.value?.[0]}><input id="c-value" inputMode="decimal" className={inputClass} value={f.value} onChange={(e) => set("value", e.target.value)} /></Field> : <div aria-hidden="true" />}
        <Field label="Description" htmlFor="c-desc"><input id="c-desc" className={inputClass} value={f.description} onChange={(e) => set("description", e.target.value)} /></Field>
        {f.type === "percentage" ? <Field label="Max discount (₹)" htmlFor="c-max"><input id="c-max" inputMode="decimal" className={inputClass} value={f.max_discount} onChange={(e) => set("max_discount", e.target.value)} /></Field> : <div aria-hidden="true" />}
        <Field label="Minimum order (₹)" htmlFor="c-min"><input id="c-min" inputMode="decimal" className={inputClass} value={f.min_order_total} onChange={(e) => set("min_order_total", e.target.value)} /></Field>
        <Field label="Total uses" htmlFor="c-limit"><input id="c-limit" inputMode="numeric" className={inputClass} placeholder="Unlimited" value={f.usage_limit} onChange={(e) => set("usage_limit", e.target.value.replace(/\D/g, ""))} /></Field>
        <Field label="Uses per customer" htmlFor="c-pc"><input id="c-pc" inputMode="numeric" className={inputClass} placeholder="Unlimited" value={f.per_customer_limit} onChange={(e) => set("per_customer_limit", e.target.value.replace(/\D/g, ""))} /></Field>
        <Field label="Expires on" htmlFor="c-ends"><input id="c-ends" type="date" className={inputClass} value={f.ends_at} onChange={(e) => set("ends_at", e.target.value)} /></Field>
        <div className="flex flex-wrap gap-6 md:col-span-3">
          <label className={checkboxLabelClass}><input type="checkbox" className="size-4 accent-primary" checked={f.first_order_only} onChange={(e) => set("first_order_only", e.target.checked)} /> First order only</label>
          <label className={checkboxLabelClass}><input type="checkbox" className="size-4 accent-primary" checked={f.is_active} onChange={(e) => set("is_active", e.target.checked)} /> Active</label>
        </div>
        <FormActions className="md:col-span-3"><Button type="submit" disabled={save.isPending || !f.code}>Save coupon</Button><Button type="button" variant="ghost" onClick={onDone}>Cancel</Button></FormActions>
      </form>
    </Panel>
  );
}
