import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { TaxonomyPage } from "@/features/admin/pages/taxonomy";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><TaxonomyPage /></Suspense>;
}
