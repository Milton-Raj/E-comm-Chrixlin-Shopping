import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { SHOP_PAGE_SIZE } from "@/features/catalog/catalog";
import { ShopBrowser } from "@/features/catalog/components/shop-browser";
import { getProducts } from "@/features/catalog/server";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("placeholder.shopTitle") };

export default function ShopPage({ searchParams }: PageProps<"/shop">) {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <header className="mb-10 text-center">
        <p className="eyebrow mb-3 text-muted-foreground">{t("home.eyebrow")}</p>
        <h1 className="font-display-tight text-5xl md:text-6xl">{t("placeholder.shopTitle")}</h1>
      </header>
      <Suspense fallback={<LoadingState lines={6} />}>
        <ShopProducts searchParams={searchParams} />
      </Suspense>
    </div>
  );
}

/**
 * First page server-rendered so the HTML already links every product (crawlers, slow phones).
 * Reading the request's search params renders this at request time; a build-time render would
 * stop at the filter bar's URL read and ship an empty grid. Product data stays cached.
 */
async function ShopProducts({ searchParams }: Pick<PageProps<"/shop">, "searchParams">) {
  await searchParams;
  const initial = await getProducts({ category: null, type: null, sort: "featured", page: 1, per_page: SHOP_PAGE_SIZE });
  return <ShopBrowser initialProducts={initial} />;
}
