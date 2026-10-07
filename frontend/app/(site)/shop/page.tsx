import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ShopBrowser } from "@/features/catalog/components/shop-browser";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("placeholder.shopTitle") };

export default function ShopPage() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <header className="mb-10 text-center">
        <p className="eyebrow mb-3 text-muted-foreground">{t("home.eyebrow")}</p>
        <h1 className="font-display-tight text-5xl md:text-6xl">{t("placeholder.shopTitle")}</h1>
      </header>
      <Suspense fallback={<LoadingState lines={6} />}>
        <ShopBrowser />
      </Suspense>
    </div>
  );
}
