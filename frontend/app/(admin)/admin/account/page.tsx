import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AdminAccountPage } from "@/features/admin/pages/account";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={6} />}><AdminAccountPage /></Suspense>;
}
