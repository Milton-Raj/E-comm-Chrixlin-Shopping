"use client";


import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { formatMoney } from "@/lib/money";
import { miscAdminApi } from "../api";
import { ExportButton } from "../components/export-button";
import { AdminPage, FilterBar, inputClass, Pagination, Panel, StatusBadge } from "../components/kit/admin-page";

export function CustomersPage() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const query = params.toString();
  const [q, setQ] = useState(params.get("q") ?? "");
  const customers = useQuery({ queryKey: ["admin", "customers", query], queryFn: () => miscAdminApi.customers(query ? `?${query}` : ""), placeholderData: (p) => p });
  const set = (key: string, value: string | null) => {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(key, value); else next.delete(key);
    if (key !== "page") next.delete("page");
    router.replace(`${pathname}${next.size ? `?${next}` : ""}`);
  };

  return (
    <AdminPage title="Customers" description="Accounts, lifetime value and activity." actions={<ExportButton type="customers" />}>
      <FilterBar>
        <form role="search" onSubmit={(e) => { e.preventDefault(); set("q", q.trim() || null); }}>
          <input aria-label="Search customers" className={inputClass} placeholder="Name or email, press Enter" value={q} onChange={(e) => setQ(e.target.value)} />
        </form>
      </FilterBar>
      {customers.isPending ? <LoadingState lines={8} /> : customers.isError ? <ErrorState onRetry={() => void customers.refetch()} /> : (
        <>
          <div className="overflow-x-auto border border-border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b border-border text-left text-xs text-muted-foreground uppercase"><tr><th className="p-3">Customer</th><th className="p-3">Orders</th><th className="p-3">Total spent</th><th className="p-3">Joined</th><th className="p-3">Status</th></tr></thead>
              <tbody className="divide-y divide-border">
                {customers.data.items.map((c) => (
                  <tr key={c.uuid} className="hover:bg-muted/40">
                    <td className="p-3"><Link href={`/admin/customers/${c.uuid}`} className="font-medium hover:text-primary">{c.name}</Link><span className="block text-xs text-muted-foreground">{c.email}</span></td>
                    <td className="p-3">{c.orders_count}</td>
                    <td className="p-3">{formatMoney(c.total_spent)}</td>
                    <td className="p-3 text-muted-foreground">{c.created_at ? new Date(c.created_at).toLocaleDateString("en-IN") : ""}</td>
                    <td className="p-3"><StatusBadge value={c.is_active ? "active" : "blocked"} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={customers.data.pagination.page} lastPage={customers.data.pagination.last_page} onChange={(n) => set("page", String(n))} />
        </>
      )}
    </AdminPage>
  );
}

export function CustomerDetailPage({ uuid }: { uuid: string }) {
  const queryClient = useQueryClient();
  const customer = useQuery({ queryKey: ["admin", "customer", uuid], queryFn: () => miscAdminApi.customer(uuid) });
  const toggle = useMutation({
    mutationFn: (active: boolean) => miscAdminApi.setCustomerActive(uuid, active),
    onSuccess: () => { toast.success("Customer updated."); void queryClient.invalidateQueries({ queryKey: ["admin", "customer", uuid] }); },
  });
  if (customer.isPending) return <LoadingState lines={8} />;
  if (customer.isError) return <ErrorState onRetry={() => void customer.refetch()} />;
  const c = customer.data;

  return (
    <AdminPage title={c.name} description={`${c.email}${c.phone ? ` · ${c.phone}` : ""} · joined ${c.created_at ? new Date(c.created_at).toLocaleDateString("en-IN") : ""}`}
      actions={<Button variant="outline" disabled={toggle.isPending} onClick={() => toggle.mutate(!c.is_active)}>{c.is_active ? "Block account" : "Unblock account"}</Button>}>
      <ul className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {[
          ["Total orders", String(c.stats.total_orders)], ["Total spent", formatMoney(c.stats.total_spent)], ["Average order", formatMoney(c.stats.average_order_value)],
          ["Last purchase", c.stats.last_purchase_at ? new Date(c.stats.last_purchase_at).toLocaleDateString("en-IN") : "—"],
          ["Wishlist", String(c.stats.wishlist_items)], ["Downloads", String(c.stats.downloads)], ["Refunded orders", String(c.stats.refunded_orders)],
          ["Email", c.email_verified ? "Verified" : "Unverified"],
        ].map(([label, value]) => <li key={label} className="border border-border bg-card p-4"><p className="eyebrow text-muted-foreground">{label}</p><p className="mt-1 text-xl font-semibold">{value}</p></li>)}
      </ul>
      <Panel title="Orders">
        <ul className="divide-y divide-border text-sm">
          {c.orders.map((o) => (
            <li key={o.uuid}><Link href={`/admin/orders/${o.order_number}`} className="flex justify-between gap-3 py-2 hover:text-primary"><span>{o.order_number}</span><StatusBadge value={o.status} /><span>{formatMoney(o.totals.total)}</span></Link></li>
          ))}
        </ul>
      </Panel>
    </AdminPage>
  );
}
