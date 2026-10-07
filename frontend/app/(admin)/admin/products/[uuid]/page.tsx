import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ProductEditorPage } from "@/features/admin/pages/product-editor";

export default function Page({ params }: PageProps<"/admin/products/[uuid]">) {
  return <Suspense fallback={<LoadingState lines={10} />}>{params.then(({ uuid }) => <ProductEditorPage uuid={uuid} />)}</Suspense>;
}
