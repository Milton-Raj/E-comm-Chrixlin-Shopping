import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { MarketingPage } from "@/features/admin/pages/marketing";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><MarketingPage /></Suspense>;
}
