import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { OrdersPage } from "@/features/admin/pages/orders";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><OrdersPage /></Suspense>;
}
