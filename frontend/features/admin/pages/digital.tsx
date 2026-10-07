"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { ApiError } from "@/services/api-client";
import { miscAdminApi } from "../api";
import { ExportButton } from "../components/export-button";
import { AdminPage, Pagination, Panel, StatusBadge } from "../components/kit/admin-page";

export function DigitalPage() {
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const entitlements = useQuery({ queryKey: ["admin", "entitlements", page], queryFn: () => miscAdminApi.entitlements(`?page=${page}`), placeholderData: (p) => p });
  const downloads = useQuery({ queryKey: ["admin", "downloads"], queryFn: miscAdminApi.downloads });
  const update = useMutation({
    mutationFn: ({ uuid, action }: { uuid: string; action: "revoke" | "restore" | "reset" }) => miscAdminApi.updateEntitlement(uuid, action),
    onSuccess: () => { toast.success("Access updated."); void queryClient.invalidateQueries({ queryKey: ["admin", "entitlements"] }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not update."),
  });

  return (
    <AdminPage title="Digital products" description="Download access per order, limits and the download log. Manage files on each product." actions={<ExportButton type="digital" />}>
      <Panel title="Download access">
        {entitlements.isPending ? <LoadingState lines={5} /> : entitlements.isError ? <ErrorState onRetry={() => void entitlements.refetch()} /> : (
          <>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="text-left text-xs text-muted-foreground uppercase"><tr><th className="p-2">Customer</th><th className="p-2">Product</th><th className="p-2">Order</th><th className="p-2">Downloads</th><th className="p-2">Status</th><th className="p-2" /></tr></thead>
                <tbody className="divide-y divide-border">
                  {entitlements.data.items.map((e) => (
                    <tr key={e.uuid}>
                      <td className="p-2">{e.email}</td><td className="p-2">{e.product}</td>
                      <td className="p-2"><Link className="underline underline-offset-4" href={`/admin/orders/${e.order_number}`}>{e.order_number}</Link></td>
                      <td className="p-2">{e.downloads_used} / {e.download_limit ?? "∞"}</td>
                      <td className="p-2"><StatusBadge value={e.status} /></td>
                      <td className="p-2 text-right whitespace-nowrap">
                        {["available", "downloaded"].includes(e.status) ? <Button size="sm" variant="ghost" onClick={() => update.mutate({ uuid: e.uuid, action: "revoke" })}>Revoke</Button> : null}
                        {e.status === "revoked" ? <Button size="sm" variant="ghost" onClick={() => update.mutate({ uuid: e.uuid, action: "restore" })}>Restore</Button> : null}
                        {e.downloads_used > 0 && e.status !== "refunded" ? <Button size="sm" variant="ghost" onClick={() => update.mutate({ uuid: e.uuid, action: "reset" })}>Reset count</Button> : null}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pagination page={entitlements.data.pagination.page} lastPage={entitlements.data.pagination.last_page} onChange={setPage} />
          </>
        )}
      </Panel>
      <Panel title="Recent downloads">
        {downloads.isPending ? <LoadingState lines={3} /> : (
          <ul className="divide-y divide-border text-sm">
            {(downloads.data ?? []).map((d, i) => (
              <li key={i} className="flex flex-wrap justify-between gap-2 py-2"><span>{d.email} · {d.file}</span><span className="text-muted-foreground"><StatusBadge value={d.status === "completed" ? "delivered" : "failed"} /> {d.ip_address} · {new Date(d.created_at).toLocaleString("en-IN")}</span></li>
            ))}
            {!downloads.data?.length ? <li className="py-2 text-muted-foreground">No downloads yet.</li> : null}
          </ul>
        )}
      </Panel>
    </AdminPage>
  );
}
