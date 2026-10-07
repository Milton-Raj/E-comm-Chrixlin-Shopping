import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { OrderView } from "@/features/orders/components/order-view";

export const metadata: Metadata = { title: "Order confirmed", robots: { index: false } };

export default function ConfirmationPage({ params, searchParams }: PageProps<"/checkout/confirmation/[orderNumber]">) {
  return (
    <div className="mx-auto max-w-5xl px-4 py-10 md:px-6 md:py-14">
      <Suspense fallback={<LoadingState lines={8} />}>
        {Promise.all([params, searchParams]).then(([{ orderNumber }, query]) => (
          <OrderView orderNumber={orderNumber} token={typeof query.token === "string" ? query.token : null} confirmation />
        ))}
      </Suspense>
    </div>
  );
}
