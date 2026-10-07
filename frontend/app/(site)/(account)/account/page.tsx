import type { Metadata } from "next";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { AccountOverview } from "@/features/account/components/account-overview";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("account.title"), robots: { index: false } };

/** Account data is fetched client-side with the session cookie (ARCHITECTURE §7). */
export default function AccountPage() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-10 md:px-6 md:py-14">
      <Suspense fallback={<LoadingState lines={4} />}>
        <AccountOverview />
      </Suspense>
    </div>
  );
}
