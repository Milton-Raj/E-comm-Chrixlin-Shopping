import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { InternationalSalesPage } from "@/features/admin/pages/international";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><InternationalSalesPage /></Suspense>;
}
