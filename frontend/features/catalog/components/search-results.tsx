"use client";

import { Search } from "lucide-react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { t } from "@/lib/i18n";
import { fetchProducts } from "../api";
import { ProductGrid } from "./product-card";

/** Product search via the API (name, brand, category, description, SKU). */
export function SearchResults() {
  const router = useRouter();
  const pathname = usePathname();
  const q = useSearchParams().get("q") ?? "";
  const [value, setValue] = useState(q);
  const search = useQuery({ queryKey: ["search", q], queryFn: () => fetchProducts({ q, per_page: 48 }), enabled: q.length > 0 });
  const results = search.data?.items ?? [];

  return (
    <div className="grid gap-10">
      <form
        role="search"
        className="mx-auto flex w-full max-w-2xl items-center gap-3 border-b border-foreground pb-2"
        onSubmit={(e) => {
          e.preventDefault();
          router.replace(value.trim() ? `${pathname}?q=${encodeURIComponent(value.trim())}` : pathname);
        }}
      >
        <Search className="size-5 text-muted-foreground" aria-hidden />
        <label htmlFor="search-q" className="sr-only">{t("catalog.searchLabel")}</label>
        <input
          id="search-q"
          type="search"
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={t("catalog.searchPlaceholder")}
          className="min-h-11 flex-1 bg-transparent text-xl outline-none placeholder:text-muted-foreground/70"
        />
      </form>

      {q ? (
        <>
          <p className="text-center text-sm text-muted-foreground" aria-live="polite">
            {t("catalog.searchResults", { q })}{search.isSuccess ? ` · ${t("catalog.results", { count: search.data.pagination.total })}` : ""}
          </p>
          {search.isPending ? null : results.length ? (
            <ProductGrid products={results} />
          ) : (
            <EmptyState
              title={t("catalog.noResultsTitle")}
              description={t("catalog.noResultsBody")}
              action={<ButtonLink href="/categories" variant="outline">{t("nav.categories")}</ButtonLink>}
            />
          )}
        </>
      ) : null}
    </div>
  );
}
