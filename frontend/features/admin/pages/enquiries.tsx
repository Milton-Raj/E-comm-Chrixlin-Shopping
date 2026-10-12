"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Mail, Phone } from "lucide-react";
import { useState } from "react";
import { toast } from "sonner";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type AdminEnquiry, type EnquiryStatus } from "../api";
import { AdminPage, Field, inputClass, Pagination, StatusBadge, textareaClass } from "../components/kit/admin-page";

const statuses: EnquiryStatus[] = ["new", "contacted", "quoted", "won", "lost"];

/** Admin → Enquiries: corporate & bulk gifting leads from /corporate-gifting. */
export function EnquiriesPage() {
  const [status, setStatus] = useState<EnquiryStatus | "">("");
  const [page, setPage] = useState(1);
  const query = `?page=${page}${status ? `&status=${status}` : ""}`;
  const list = useQuery({ queryKey: ["admin", "enquiries", query], queryFn: () => miscAdminApi.enquiries(query) });

  return (
    <AdminPage title="Enquiries" description="Corporate and bulk gifting requests from the Corporate gifting page. Each one is also emailed to your support address.">
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filter by status">
        {(["", ...statuses] as const).map((s) => (
          <button key={s || "all"} type="button" aria-pressed={status === s} onClick={() => { setStatus(s); setPage(1); }}
            className={cn("inline-flex min-h-10 items-center border px-3 text-sm capitalize", status === s ? "border-foreground bg-foreground text-background" : "border-border hover:border-foreground")}>
            {s || "All"}
          </button>
        ))}
      </div>
      {list.isPending ? <LoadingState lines={6} /> : list.isError ? <ErrorState onRetry={() => void list.refetch()} /> : list.data.items.length === 0 ? (
        <EmptyState title="No enquiries yet" description="Share your Corporate gifting page with businesses and wedding planners to get started." />
      ) : (
        <>
          <ul className="grid gap-3">{list.data.items.map((e) => <EnquiryCard key={e.uuid} enquiry={e} />)}</ul>
          <Pagination page={list.data.pagination.page} lastPage={list.data.pagination.last_page} onChange={setPage} />
        </>
      )}
    </AdminPage>
  );
}

function EnquiryCard({ enquiry: e }: { enquiry: AdminEnquiry }) {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState(e.status);
  const [notes, setNotes] = useState(e.notes ?? "");
  const dirty = status !== e.status || notes !== (e.notes ?? "");
  const save = useMutation({
    mutationFn: () => miscAdminApi.updateEnquiry(e.uuid, { status, notes: notes.trim() || null }),
    onSuccess: () => { toast.success("Enquiry updated."); void queryClient.invalidateQueries({ queryKey: ["admin", "enquiries"] }); },
    onError: (err) => toast.error(err instanceof ApiError ? err.message : "Could not save."),
  });
  const facts = [
    e.quantity ? `${e.quantity} gifts` : null, e.budget ? `${e.budget} each` : null, e.occasion,
    e.needed_by ? `by ${new Date(e.needed_by).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}` : null,
  ].filter(Boolean);

  return (
    <li className="grid gap-4 border border-border bg-card p-4 md:grid-cols-3">
      <div className="grid content-start gap-2 md:col-span-2">
        <div className="flex flex-wrap items-center gap-2">
          <p className="font-medium">{e.name}{e.company ? <span className="text-muted-foreground"> · {e.company}</span> : null}</p>
          <StatusBadge value={e.status} />
          {e.created_at ? <span className="text-xs text-muted-foreground">{new Date(e.created_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" })}</span> : null}
        </div>
        {facts.length ? <p className="text-sm">{facts.join(" · ")}</p> : null}
        <p className="text-sm whitespace-pre-line text-muted-foreground">{e.message}</p>
        <div className="flex flex-wrap gap-x-4 text-sm">
          <a href={`mailto:${e.email}`} className="inline-flex min-h-11 items-center gap-1.5 underline-offset-4 hover:underline"><Mail className="size-4" aria-hidden />{e.email}</a>
          {e.phone ? <a href={`tel:${e.phone.replace(/\s/g, "")}`} className="inline-flex min-h-11 items-center gap-1.5 underline-offset-4 hover:underline"><Phone className="size-4" aria-hidden />{e.phone}</a> : null}
        </div>
      </div>
      <form className="grid content-start gap-3" onSubmit={(ev) => { ev.preventDefault(); save.mutate(); }}>
        <Field label="Status" htmlFor={`st-${e.uuid}`}>
          <select id={`st-${e.uuid}`} className={cn(inputClass, "capitalize")} value={status} onChange={(ev) => setStatus(ev.target.value as EnquiryStatus)}>
            {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </Field>
        <Field label="Private notes" htmlFor={`nt-${e.uuid}`}>
          <textarea id={`nt-${e.uuid}`} className={cn(textareaClass, "min-h-20")} value={notes} onChange={(ev) => setNotes(ev.target.value)} maxLength={5000} />
        </Field>
        <Button type="submit" size="sm" disabled={!dirty || save.isPending}>{save.isPending ? "Saving…" : "Save"}</Button>
      </form>
    </li>
  );
}
