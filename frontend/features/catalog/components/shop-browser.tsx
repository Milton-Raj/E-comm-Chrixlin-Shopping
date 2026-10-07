"use client";

import { useQuery } from "@tanstack/react-query";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { fetchCategories, fetchProducts } from "../api";
import { sortOptions } from "../catalog";
import type { ProductSort, ProductType } from "../types";
import { ProductGrid } from "./product-card";

const sorts = new Set<string>(sortOptions.map((s) => s.value));

/**
 * Category / type / sort filters kept in the URL (shareable, back-button friendly)
 * and applied without a full page reload (PRD §24).
 */
export function ShopBrowser({ fixedCategory }: { fixedCategory?: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();

  const category = fixedCategory ?? params.get("category");
  const rawType = params.get("type");
  const type: ProductType | null = rawType === "physical" || rawType === "digital" ? rawType : null;
  const rawSort = params.get("sort");
  const sort = (rawSort && sorts.has(rawSort) ? rawSort : "featured") as ProductSort;
  const page = Math.max(1, Number(params.get("page") ?? 1) || 1);

  const products = useQuery({
    queryKey: ["products", { category, type, sort, page }],
    queryFn: () => fetchProducts({ category, type, sort, page, per_page: 24 }),
    placeholderData: (previous) => previous,
  });
  const categories = useQuery({ queryKey: ["categories"], queryFn: fetchCategories, staleTime: 300_000 });

  const update = (key: string, value: string | null) => {
    const next = new URLSearchParams(params.toString());
    if (value) next.set(key, value);
    else next.delete(key);
    if (key !== "page") next.delete("page");
    router.replace(`${pathname}${next.size ? `?${next}` : ""}`, { scroll: false });
  };

  const chip = (active: boolean) =>
    cn(
      "eyebrow inline-flex min-h-11 items-center whitespace-nowrap border px-4 transition-colors",
      active ? "border-foreground bg-foreground text-background" : "border-border hover:border-foreground",
    );

  const total = products.data?.pagination.total ?? 0;
  const lastPage = products.data?.pagination.last_page ?? 1;

  return (
    <div className="grid min-w-0 grid-cols-1 gap-8">
      <div className="flex min-w-0 flex-col gap-4 border-y border-border py-4 lg:flex-row lg:items-center lg:justify-between">
        {/* min-w-0 keeps the chip row scrolling inside itself instead of widening the page on phones. */}
        <div className="-mx-4 flex min-w-0 gap-2 overflow-x-auto px-4 lg:mx-0 lg:flex-wrap lg:px-0">
          {!fixedCategory ? (
            <>
              <button type="button" className={chip(!category)} aria-pressed={!category} onClick={() => update("category", null)}>
                {t("catalog.allCategories")}
              </button>
              {(categories.data ?? []).map((c) => (
                <button key={c.slug} type="button" className={chip(category === c.slug)} aria-pressed={category === c.slug} onClick={() => update("category", c.slug)}>
                  {c.name}
                </button>
              ))}
            </>
          ) : (
            (["physical", "digital"] as const).map((value) => (
              <button key={value} type="button" className={chip(type === value)} aria-pressed={type === value} onClick={() => update("type", type === value ? null : value)}>
                {t(value === "digital" ? "catalog.digital" : "catalog.physical")}
              </button>
            ))
          )}
        </div>
        <div className="flex items-center justify-between gap-4 lg:justify-end">
          <p className="text-sm text-muted-foreground" aria-live="polite">{products.isSuccess ? t("catalog.results", { count: total }) : " "}</p>
          <label className="flex items-center gap-2">
            <span className="eyebrow text-muted-foreground">{t("catalog.sortBy")}</span>
            <select
              value={sort}
              onChange={(e) => update("sort", e.target.value === "featured" ? null : e.target.value)}
              className="min-h-11 border border-border bg-background px-3 text-sm"
            >
              {sortOptions.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </label>
        </div>
      </div>

      {!fixedCategory && type ? (
        <p className="text-sm">
          {t("catalog.filterType")}: <strong>{t(type === "digital" ? "catalog.digital" : "catalog.physical")}</strong>{" "}
          <button type="button" className="ml-2 inline-flex min-h-11 items-center underline underline-offset-4" onClick={() => update("type", null)}>{t("catalog.clearFilters")}</button>
        </p>
      ) : null}

      {products.isPending ? (
        <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 md:gap-x-6 xl:grid-cols-4" aria-busy="true">
          {Array.from({ length: 8 }, (_, i) => (
            <li key={i} className="grid gap-3">
              <Skeleton className="aspect-4/5 w-full" />
              <Skeleton className="h-4 w-2/3" />
              <Skeleton className="h-4 w-1/3" />
            </li>
          ))}
        </ul>
      ) : products.isError ? (
        <ErrorState onRetry={() => void products.refetch()} />
      ) : products.data.items.length ? (
        <>
          <ProductGrid products={products.data.items} preloadCount={4} />
          {lastPage > 1 ? (
            <nav aria-label="Pagination" className="flex items-center justify-center gap-2">
              <button type="button" className={chip(false)} disabled={page <= 1} onClick={() => update("page", String(page - 1))}>Previous</button>
              <span className="text-sm text-muted-foreground">Page {page} of {lastPage}</span>
              <button type="button" className={chip(false)} disabled={page >= lastPage} onClick={() => update("page", String(page + 1))}>Next</button>
            </nav>
          ) : null}
        </>
      ) : (
        <EmptyState
          title={t("catalog.noResultsTitle")}
          description={t("catalog.noResultsBody")}
          action={<ButtonLink href="/shop" variant="outline">{t("catalog.clearFilters")}</ButtonLink>}
        />
      )}
    </div>
  );
}
