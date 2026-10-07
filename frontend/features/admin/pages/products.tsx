"use client";


import { useQuery } from "@tanstack/react-query";
import { Package, Plus } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useState } from "react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { formatMoney } from "@/lib/money";
import { adminApi } from "../api";
import { ExportButton } from "../components/export-button";
import { AdminPage, FilterBar, inputClass, Pagination, StatusBadge } from "../components/kit/admin-page";

export function ProductsPage() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const [q, setQ] = useState(params.get("q") ?? "");
  const query = params.toString();
  const products = useQuery({ queryKey: ["admin", "products", query], queryFn: () => adminApi.products(query ? `?${query}` : ""), placeholderData: (p) => p });

  const set = (key: string, value: string | null) => {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(key, value);
    else next.delete(key);
    if (key !== "page") next.delete("page");
    router.replace(`${pathname}${next.size ? `?${next}` : ""}`);
  };

  return (
    <AdminPage title="Products" description="Physical and digital products, variants, pricing and stock." actions={<><ExportButton type="products" /><ButtonLink href="/admin/products/new"><Plus className="size-4" aria-hidden /> New product</ButtonLink></>}>
      <FilterBar>
        <form onSubmit={(e) => { e.preventDefault(); set("q", q.trim() || null); }} role="search">
          <label htmlFor="product-search" className="sr-only">Search products</label>
          <input id="product-search" className={inputClass} placeholder="Search name or SKU, press Enter" value={q} onChange={(e) => setQ(e.target.value)} />
        </form>
        <select aria-label="Status" className={inputClass} value={params.get("status") ?? ""} onChange={(e) => set("status", e.target.value || null)}>
          <option value="">All statuses</option><option value="active">Active</option><option value="draft">Draft</option><option value="archived">Archived</option>
        </select>
        <select aria-label="Type" className={inputClass} value={params.get("type") ?? ""} onChange={(e) => set("type", e.target.value || null)}>
          <option value="">All types</option><option value="physical">Physical</option><option value="digital">Digital</option>
        </select>
      </FilterBar>

      {products.isPending ? <LoadingState lines={8} /> : products.isError ? <ErrorState onRetry={() => void products.refetch()} /> : !products.data.items.length ? (
        <EmptyState icon={<Package />} title="No products found" action={<ButtonLink href="/admin/products/new">Create a product</ButtonLink>} />
      ) : (
        <>
          <div className="overflow-x-auto border border-border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b border-border text-left text-xs text-muted-foreground uppercase">
                <tr><th className="p-3">Product</th><th className="p-3">Status</th><th className="p-3">Type</th><th className="p-3">Price</th><th className="p-3">Stock</th><th className="p-3">Sold</th></tr>
              </thead>
              <tbody className="divide-y divide-border">
                {products.data.items.map((p) => (
                  <tr key={p.uuid} className="hover:bg-muted/40">
                    <td className="p-3">
                      <Link href={`/admin/products/${p.uuid}`} className="flex items-center gap-3 font-medium hover:text-primary">
                        <span className="relative size-10 shrink-0 overflow-hidden bg-muted">{p.image ? <Image src={p.image} alt="" fill sizes="40px" className="object-cover" /> : null}</span>
                        <span>{p.name}<span className="block text-xs font-normal text-muted-foreground">{p.category?.name ?? "Uncategorised"} · {p.variant_count} variant{p.variant_count === 1 ? "" : "s"}</span></span>
                      </Link>
                    </td>
                    <td className="p-3"><StatusBadge value={p.status} /></td>
                    <td className="p-3 capitalize">{p.product_type}</td>
                    <td className="p-3 whitespace-nowrap">{formatMoney(p.price)}{p.max_price.amount > p.price.amount ? ` – ${formatMoney(p.max_price)}` : ""}</td>
                    <td className="p-3">{p.stock_available === null ? "∞" : <span className={p.stock_available <= 5 ? "font-medium text-primary" : ""}>{p.stock_available}</span>}</td>
                    <td className="p-3">{p.units_sold}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={products.data.pagination.page} lastPage={products.data.pagination.last_page} onChange={(n) => set("page", String(n))} />
        </>
      )}
    </AdminPage>
  );
}
