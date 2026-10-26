import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { CustomerDetailPage } from "@/features/admin/pages/customers";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page({ params }: PageProps<"/admin/customers/[customerNumber]">) {
  return <Suspense fallback={<LoadingState lines={8} />}>{params.then(({ customerNumber }) => <CustomerDetailPage customerNumber={decodeURIComponent(customerNumber)} />)}</Suspense>;
}
