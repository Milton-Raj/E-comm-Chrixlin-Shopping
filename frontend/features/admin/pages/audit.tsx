"use client";

import { useQuery } from "@tanstack/react-query";
import { useState } from "react";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { miscAdminApi } from "../api";
import { AdminPage, Pagination } from "../components/kit/admin-page";

const summarise = (data: Record<string, unknown> | null) => (data ? Object.entries(data).map(([k, v]) => `${k}: ${typeof v === "object" ? JSON.stringify(v) : String(v)}`).join(", ") : "—");

export function AuditPage() {
  const [page, setPage] = useState(1);
  const audit = useQuery({ queryKey: ["admin", "audit", page], queryFn: () => miscAdminApi.audit(page), placeholderData: (p) => p });

  return (
    <AdminPage title="Audit log" description="Every sensitive admin action: who, what changed, when and from where (PRD §65).">
      {audit.isPending ? <LoadingState lines={10} /> : audit.isError ? <ErrorState onRetry={() => void audit.refetch()} /> : (
        <>
          <div className="overflow-x-auto border border-border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b border-border text-left text-xs text-muted-foreground uppercase"><tr><th className="p-3">When</th><th className="p-3">Who</th><th className="p-3">Action</th><th className="p-3">Before</th><th className="p-3">After</th><th className="p-3">IP</th></tr></thead>
              <tbody className="divide-y divide-border">
                {audit.data.items.map((a) => (
                  <tr key={a.uuid} className="align-top">
                    <td className="p-3 whitespace-nowrap text-muted-foreground">{new Date(a.created_at).toLocaleString("en-IN", { dateStyle: "short", timeStyle: "short" })}</td>
                    <td className="p-3">{a.actor}</td>
                    <td className="p-3 font-mono text-xs">{a.action}</td>
                    <td className="max-w-xs p-3 text-xs break-words text-muted-foreground">{summarise(a.before)}</td>
                    <td className="max-w-xs p-3 text-xs break-words">{summarise(a.after)}</td>
                    <td className="p-3 font-mono text-xs text-muted-foreground">{a.ip_address ?? "—"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={audit.data.pagination.page} lastPage={audit.data.pagination.last_page} onChange={setPage} />
        </>
      )}
    </AdminPage>
  );
}
