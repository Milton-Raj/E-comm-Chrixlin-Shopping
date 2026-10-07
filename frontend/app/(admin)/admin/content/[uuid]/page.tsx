import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { PageEditor } from "@/features/admin/pages/content";

export default function Page({ params }: PageProps<"/admin/content/[uuid]">) {
  return <Suspense fallback={<LoadingState lines={8} />}>{params.then(({ uuid }) => <PageEditor uuid={uuid} />)}</Suspense>;
}
