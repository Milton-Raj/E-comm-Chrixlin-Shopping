"use client";


import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { ApiError } from "@/services/api-client";
import { inventoryApi, type InventoryRow } from "../api";
import { ExportButton } from "../components/export-button";
import { AdminPage, checkboxLabelClass, Field, FilterBar, inputClass, Pagination } from "../components/kit/admin-page";

export function InventoryPage() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const query = params.toString();
  const [q, setQ] = useState(params.get("q") ?? "");
  const [selected, setSelected] = useState<InventoryRow | null>(null);
  const rows = useQuery({ queryKey: ["admin", "inventory", query], queryFn: () => inventoryApi.list(query ? `?${query}` : ""), placeholderData: (p) => p });

  const set = (key: string, value: string | null) => {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(key, value); else next.delete(key);
    if (key !== "page") next.delete("page");
    router.replace(`${pathname}${next.size ? `?${next}` : ""}`);
  };

  return (
    <AdminPage title="Inventory" description="Stock per variant. Every change is written to an append-only ledger." actions={<ExportButton type="inventory" />}>
      <FilterBar>
        <form role="search" onSubmit={(e) => { e.preventDefault(); set("q", q.trim() || null); }}>
          <input aria-label="Search" className={inputClass} placeholder="Product or SKU, press Enter" value={q} onChange={(e) => setQ(e.target.value)} />
        </form>
        <label className={checkboxLabelClass}><input type="checkbox" className="size-4 accent-primary" checked={params.get("low") === "1"} onChange={(e) => set("low", e.target.checked ? "1" : null)} /> Low stock only</label>
      </FilterBar>
      {rows.isPending ? <LoadingState lines={8} /> : rows.isError ? <ErrorState onRetry={() => void rows.refetch()} /> : (
        <>
          <div className="overflow-x-auto border border-border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b border-border text-left text-xs text-muted-foreground uppercase"><tr><th className="p-3">Product</th><th className="p-3">SKU</th><th className="p-3">On hand</th><th className="p-3">Reserved</th><th className="p-3">Available</th><th className="p-3" /></tr></thead>
              <tbody className="divide-y divide-border">
                {rows.data.items.map((r) => (
                  <tr key={r.variant_uuid} className={r.low ? "bg-primary/5" : ""}>
                    <td className="p-3"><Link href={`/admin/products/${r.product_uuid}`} className="font-medium hover:text-primary">{r.product_name}</Link>{r.variant_name ? <span className="block text-xs text-muted-foreground">{r.variant_name}</span> : null}</td>
                    <td className="p-3 font-mono text-xs">{r.sku}</td>
                    <td className="p-3">{r.on_hand}</td>
                    <td className="p-3">{r.reserved}</td>
                    <td className={`p-3 font-medium ${r.low ? "text-primary" : ""}`}>{r.available}</td>
                    <td className="p-3 text-right"><Button size="sm" variant="outline" onClick={() => setSelected(r)}>Adjust</Button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={rows.data.pagination.page} lastPage={rows.data.pagination.last_page} onChange={(n) => set("page", String(n))} />
        </>
      )}
      <AdjustDialog row={selected} onClose={() => setSelected(null)} />
    </AdminPage>
  );
}

function AdjustDialog({ row, onClose }: { row: InventoryRow | null; onClose: () => void }) {
  const queryClient = useQueryClient();
  const [type, setType] = useState("received");
  const [quantity, setQuantity] = useState("");
  const [note, setNote] = useState("");
  const ledger = useQuery({ queryKey: ["admin", "ledger", row?.variant_uuid], queryFn: () => inventoryApi.ledger(row!.variant_uuid), enabled: Boolean(row) });
  const adjust = useMutation({
    mutationFn: () => inventoryApi.adjust(row!.variant_uuid, { type, quantity: Number(quantity), note: note || undefined }),
    onSuccess: (r) => {
      toast.success(`Stock updated — ${r.available} available.`);
      setQuantity(""); setNote("");
      void queryClient.invalidateQueries({ queryKey: ["admin", "inventory"] });
      void ledger.refetch();
    },
    onError: (e) => toast.error(e instanceof ApiError ? (Object.values(e.fieldErrors)[0]?.[0] ?? e.message) : "Could not update stock."),
  });

  return (
    <Dialog open={Boolean(row)} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Adjust stock</DialogTitle>
          <DialogDescription>{row?.product_name} {row?.variant_name ? `· ${row.variant_name}` : ""} — {row?.on_hand} on hand, {row?.reserved} reserved</DialogDescription>
        </DialogHeader>
        <form className="grid gap-3" onSubmit={(e) => { e.preventDefault(); adjust.mutate(); }}>
          <Field label="Reason" htmlFor="adj-type" required>
            <select id="adj-type" className={inputClass} value={type} onChange={(e) => setType(e.target.value)}>
              <option value="received">Stock received (+)</option><option value="return">Customer return (+)</option><option value="damage">Damaged / lost (−)</option><option value="adjustment">Count correction (±)</option>
            </select>
          </Field>
          <Field label="Quantity" htmlFor="adj-qty" required hint={type === "adjustment" ? "Use a negative number to reduce." : undefined}><input id="adj-qty" inputMode="numeric" className={inputClass} value={quantity} onChange={(e) => setQuantity(e.target.value.replace(/[^\d-]/g, ""))} required /></Field>
          <Field label="Note" htmlFor="adj-note"><input id="adj-note" className={inputClass} placeholder="e.g. PO-1042" value={note} onChange={(e) => setNote(e.target.value)} /></Field>
          <Button type="submit" disabled={!quantity || quantity === "-" || adjust.isPending}>Record change</Button>
        </form>
        <div className="max-h-56 overflow-y-auto border-t border-border pt-3">
          <p className="eyebrow mb-2">Ledger</p>
          <ul className="grid gap-1 text-xs">
            {(ledger.data ?? []).map((l, i) => (
              <li key={i} className="flex justify-between gap-2"><span className="capitalize">{l.type}{l.note ? ` · ${l.note}` : ""}{l.actor ? ` · ${l.actor}` : ""}</span><span className={l.quantity < 0 ? "text-primary" : ""}>{l.quantity > 0 ? `+${l.quantity}` : l.quantity} → {l.balance_after}</span></li>
            ))}
          </ul>
        </div>
      </DialogContent>
    </Dialog>
  );
}
