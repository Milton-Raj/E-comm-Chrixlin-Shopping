import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ReviewsPage } from "@/features/admin/pages/reviews";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><ReviewsPage /></Suspense>;
}
