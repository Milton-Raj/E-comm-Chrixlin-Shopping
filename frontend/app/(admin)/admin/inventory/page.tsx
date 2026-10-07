import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { InventoryPage } from "@/features/admin/pages/inventory";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><InventoryPage /></Suspense>;
}
