import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ProductsPage } from "@/features/admin/pages/products";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><ProductsPage /></Suspense>;
}
