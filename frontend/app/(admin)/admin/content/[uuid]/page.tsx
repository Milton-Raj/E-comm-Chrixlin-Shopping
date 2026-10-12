import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { PageEditor } from "@/features/admin/pages/content";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page({ params }: PageProps<"/admin/content/[uuid]">) {
  return <Suspense fallback={<LoadingState lines={8} />}>{params.then(({ uuid }) => <PageEditor uuid={uuid} />)}</Suspense>;
}
