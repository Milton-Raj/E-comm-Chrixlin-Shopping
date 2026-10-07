import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { CouponsPage } from "@/features/admin/pages/coupons";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><CouponsPage /></Suspense>;
}
