import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { CheckoutPage } from "@/features/checkout/components/checkout-page";

export const metadata: Metadata = { title: "Checkout", robots: { index: false } };

export default function Page() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-10 md:px-6 md:py-14">
      <h1 className="font-display-tight mb-8 text-4xl md:text-5xl">Checkout</h1>
      <Suspense fallback={<LoadingState lines={8} />}>
        <CheckoutPage />
      </Suspense>
    </div>
  );
}
