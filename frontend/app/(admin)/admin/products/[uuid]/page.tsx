import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ProductEditorPage } from "@/features/admin/pages/product-editor";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page({ params }: PageProps<"/admin/products/[uuid]">) {
  return <Suspense fallback={<LoadingState lines={10} />}>{params.then(({ uuid }) => <ProductEditorPage uuid={uuid} />)}</Suspense>;
}
