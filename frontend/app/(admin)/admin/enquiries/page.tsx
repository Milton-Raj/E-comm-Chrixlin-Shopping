import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { EnquiriesPage } from "@/features/admin/pages/enquiries";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><EnquiriesPage /></Suspense>;
}
