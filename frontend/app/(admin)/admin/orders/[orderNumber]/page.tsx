import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { OrderDetailPage } from "@/features/admin/pages/orders";

export default function Page({ params }: PageProps<"/admin/orders/[orderNumber]">) {
  return <Suspense fallback={<LoadingState lines={8} />}>{params.then(({ orderNumber }) => <OrderDetailPage orderNumber={orderNumber} />)}</Suspense>;
}
