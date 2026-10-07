"use client";

import { useQuery, useQueryClient } from "@tanstack/react-query";
import { Download } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";
import { ButtonLink } from "@/components/button-link";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { useCurrentUser } from "@/features/auth/hooks";
import { ordersApi } from "../api";
import { DownloadButton } from "./download-button";

export function DownloadsList() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { data: user, isPending: userPending } = useCurrentUser();
  const downloads = useQuery({ queryKey: ["downloads"], queryFn: ordersApi.downloads, enabled: Boolean(user) });

  useEffect(() => {
    if (!userPending && !user) router.replace("/login?next=/downloads");
  }, [user, userPending, router]);

  if (userPending || !user || downloads.isPending) return <LoadingState lines={4} />;
  if (downloads.isError) return <ErrorState onRetry={() => void downloads.refetch()} />;
  if (!downloads.data.length) return <EmptyState icon={<Download />} title="No downloads yet" description="Digital products you buy appear here, ready to download." action={<ButtonLink href="/shop?type=digital">Explore digital editions</ButtonLink>} />;

  return (
    <ul className="grid gap-4">
      {downloads.data.map((d) => (
        <li key={d.uuid} className="grid gap-3 border border-border p-5">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <p className="text-lg font-semibold">{d.product?.name}</p>
            <Link href={`/orders/${d.order_number}`} className="text-sm text-muted-foreground underline underline-offset-4">{d.order_number}</Link>
          </div>
          <p className="text-sm text-muted-foreground">
            {d.download_limit ? `${d.downloads_used} of ${d.download_limit} downloads used` : `${d.downloads_used} downloads`}
            {d.expires_at ? ` · access until ${new Date(d.expires_at).toLocaleDateString("en-IN")}` : " · lifetime access"}
            {!d.usable ? ` · ${d.status === "refunded" ? "refunded" : d.status === "revoked" ? "access removed" : "no downloads remaining"}` : ""}
          </p>
          <div className="flex flex-wrap gap-2">
            {d.files.map((f) => (
              <DownloadButton key={f.uuid} entitlement={d.uuid} file={f.uuid} name={f.name} disabled={!d.usable} onDownloaded={() => setTimeout(() => void queryClient.invalidateQueries({ queryKey: ["downloads"] }), 1500)} />
            ))}
          </div>
        </li>
      ))}
    </ul>
  );
}
