import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { OrderDetailPage } from "@/features/admin/pages/orders";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page({ params }: PageProps<"/admin/orders/[orderNumber]">) {
  return <Suspense fallback={<LoadingState lines={8} />}>{params.then(({ orderNumber }) => <OrderDetailPage orderNumber={orderNumber} />)}</Suspense>;
}
