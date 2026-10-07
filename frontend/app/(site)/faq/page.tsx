import type { Metadata } from "next";
import { Suspense } from "react";
import { ContentPageBody } from "@/components/content/content-page";
import { LoadingState } from "@/components/states/loading-state";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("placeholder.faqTitle") };

export default function FaqPage() {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12 md:px-6 md:py-16">
      <Suspense fallback={<LoadingState lines={6} />}>
        <ContentPageBody slug="faq" fallbackTitle={t("placeholder.faqTitle")} />
      </Suspense>
    </div>
  );
}
