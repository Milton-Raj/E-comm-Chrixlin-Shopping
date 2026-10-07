import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { SettingsPage } from "@/features/admin/pages/settings";

export default function Page() {
  return <Suspense fallback={<LoadingState lines={8} />}><SettingsPage /></Suspense>;
}
