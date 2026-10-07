import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { CustomersPage } from "@/features/admin/pages/customers";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><CustomersPage /></Suspense>;
}
