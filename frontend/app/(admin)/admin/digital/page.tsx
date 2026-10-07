import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { DigitalPage } from "@/features/admin/pages/digital";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><DigitalPage /></Suspense>;
}
