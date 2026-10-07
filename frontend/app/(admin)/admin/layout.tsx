import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AdminGate } from "@/features/admin/components/admin-gate";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: { default: t("admin.title"), template: `%s · ${t("admin.title")}` }, robots: { index: false, follow: false } };

/** Staff-only area. The gate is UX; the API enforces staff, 2FA and permissions. */
export default function AdminLayout({ children }: LayoutProps<"/admin">) {
  return (
    <Suspense fallback={<div className="mx-auto max-w-5xl p-6"><LoadingState lines={6} /></div>}>
      <AdminGate>{children}</AdminGate>
    </Suspense>
  );
}
