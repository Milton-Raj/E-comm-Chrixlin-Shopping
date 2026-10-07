import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AuditPage } from "@/features/admin/pages/audit";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><AuditPage /></Suspense>;
}
