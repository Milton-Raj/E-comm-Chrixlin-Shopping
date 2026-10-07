import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { OrdersList } from "@/features/orders/components/orders-list";

export const metadata: Metadata = { title: "Your orders", robots: { index: false } };

export default function OrdersPage() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-10 md:px-6 md:py-14">
      <h1 className="font-display-tight mb-8 text-5xl">Your orders</h1>
      <Suspense fallback={<LoadingState lines={5} />}>
        <OrdersList />
      </Suspense>
    </div>
  );
}
