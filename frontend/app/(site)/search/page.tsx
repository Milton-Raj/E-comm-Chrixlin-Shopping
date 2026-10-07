import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { SearchResults } from "@/features/catalog/components/search-results";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("placeholder.searchTitle"), robots: { index: false } };

export default function SearchPage() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <h1 className="font-display-tight mb-10 text-center text-5xl md:text-6xl">{t("placeholder.searchTitle")}</h1>
      <Suspense fallback={<LoadingState lines={2} />}>
        <SearchResults />
      </Suspense>
    </div>
  );
}
