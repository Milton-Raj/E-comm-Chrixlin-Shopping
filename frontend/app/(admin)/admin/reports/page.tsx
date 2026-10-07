import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ReportsPage } from "@/features/admin/pages/reports";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><ReportsPage /></Suspense>;
}
