import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { WishlistPage } from "@/features/account/components/wishlist-page";

export const metadata: Metadata = { title: "Wishlist", robots: { index: false } };

export default function Page() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <h1 className="font-display-tight mb-10 text-5xl md:text-6xl">Wishlist</h1>
      <Suspense fallback={<LoadingState lines={4} />}>
        <WishlistPage />
      </Suspense>
    </div>
  );
}
