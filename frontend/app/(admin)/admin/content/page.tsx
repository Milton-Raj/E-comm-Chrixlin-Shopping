import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ContentPage } from "@/features/admin/pages/content";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><ContentPage /></Suspense>;
}
