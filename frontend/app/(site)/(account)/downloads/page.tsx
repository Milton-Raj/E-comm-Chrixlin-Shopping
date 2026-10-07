import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { DownloadsList } from "@/features/orders/components/downloads-list";

export const metadata: Metadata = { title: "Downloads", robots: { index: false } };

export default function DownloadsPage() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-10 md:px-6 md:py-14">
      <h1 className="font-display-tight mb-8 text-5xl">Downloads</h1>
      <Suspense fallback={<LoadingState lines={4} />}>
        <DownloadsList />
      </Suspense>
    </div>
  );
}
