import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { CustomerDetailPage } from "@/features/admin/pages/customers";

export default function Page({ params }: PageProps<"/admin/customers/[uuid]">) {
  return <Suspense fallback={<LoadingState lines={8} />}>{params.then(({ uuid }) => <CustomerDetailPage uuid={uuid} />)}</Suspense>;
}
