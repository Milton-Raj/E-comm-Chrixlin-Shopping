import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { StaffPage } from "@/features/admin/pages/staff";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={6} />}><StaffPage /></Suspense>;
}
