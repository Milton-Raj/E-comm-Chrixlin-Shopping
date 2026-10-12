"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { BadgeCheck, Star } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import { toast } from "sonner";
import { EmptyState } from "@/components/states/empty-state";
import { ErrorState } from "@/components/states/error-state";
import { LoadingState } from "@/components/states/loading-state";
import { Button } from "@/components/ui/button";
import { productHref } from "@/features/catalog/catalog";
import { cn } from "@/lib/utils";
import { ApiError } from "@/services/api-client";
import { miscAdminApi, type AdminReview } from "../api";
import { AdminPage, Pagination, StatusBadge } from "../components/kit/admin-page";

type Tab = AdminReview["status"];

/** Admin → Reviews: nothing is public until approved here. */
export function ReviewsPage() {
  const [tab, setTab] = useState<Tab>("pending");
  const [page, setPage] = useState(1);
  const query = `?status=${tab}&page=${page}`;
  const list = useQuery({ queryKey: ["admin", "reviews", query], queryFn: () => miscAdminApi.reviews(query) });

  return (
    <AdminPage title="Reviews" description="Customer reviews and photos appear on product pages only after you approve them. Approved reviews update the product's star rating.">
      <div className="flex flex-wrap gap-2" role="group" aria-label="Filter reviews">
        {(["pending", "approved", "rejected"] as const).map((s) => (
          <button key={s} type="button" aria-pressed={tab === s} onClick={() => { setTab(s); setPage(1); }}
            className={cn("inline-flex min-h-10 items-center border px-3 text-sm capitalize", tab === s ? "border-foreground bg-foreground text-background" : "border-border hover:border-foreground")}>
            {s === "pending" ? "To review" : s}
          </button>
        ))}
      </div>
      {list.isPending ? <LoadingState lines={6} /> : list.isError ? <ErrorState onRetry={() => void list.refetch()} /> : list.data.items.length === 0 ? (
        <EmptyState title={tab === "pending" ? "All caught up" : `No ${tab} reviews`} description={tab === "pending" ? "New customer reviews will appear here for approval." : undefined} />
      ) : (
        <>
          <ul className="grid gap-3">{list.data.items.map((r) => <ReviewCard key={r.uuid} review={r} />)}</ul>
          <Pagination page={list.data.pagination.page} lastPage={list.data.pagination.last_page} onChange={setPage} />
        </>
      )}
    </AdminPage>
  );
}

function ReviewCard({ review: r }: { review: AdminReview }) {
  const queryClient = useQueryClient();
  const moderate = useMutation({
    mutationFn: (status: "approved" | "rejected") => miscAdminApi.moderateReview(r.uuid, status),
    onSuccess: (_, status) => { toast.success(status === "approved" ? "Review published." : "Review rejected."); void queryClient.invalidateQueries({ queryKey: ["admin", "reviews"] }); },
    onError: (e) => toast.error(e instanceof ApiError ? e.message : "Could not update the review."),
  });

  return (
    <li className="grid gap-3 border border-border bg-card p-4">
      <div className="flex flex-wrap items-center gap-2 text-sm">
        {r.product ? <Link href={productHref(r.product)} target="_blank" className="font-medium underline-offset-4 hover:underline">{r.product.name}</Link> : <span className="font-medium">Removed product</span>}
        <StatusBadge value={r.status} />
        {r.verified ? <span className="inline-flex items-center gap-1 text-primary"><BadgeCheck className="size-3.5" aria-hidden />Verified purchase</span> : <span className="text-muted-foreground">Not a verified buyer</span>}
      </div>
      <div className="flex items-center gap-2">
        <span className="flex" role="img" aria-label={`${r.rating} out of 5 stars`}>
          {Array.from({ length: 5 }, (_, i) => <Star key={i} aria-hidden className={cn("size-4", i < r.rating ? "fill-brand-grullo text-brand-grullo" : "text-border")} />)}
        </span>
        {r.title ? <p className="font-medium">{r.title}</p> : null}
      </div>
      <p className="text-sm whitespace-pre-line">{r.body}</p>
      {r.photos.length ? (
        <ul className="flex flex-wrap gap-2">
          {r.photos.map((p, i) => (
            <li key={p.url}><a href={p.url} target="_blank" rel="noopener noreferrer" className="relative block size-24 overflow-hidden bg-muted" aria-label={`Open photo ${i + 1}`}><Image src={p.url} alt="" fill sizes="96px" className="object-cover" /></a></li>
          ))}
        </ul>
      ) : null}
      <p className="text-xs text-muted-foreground">
        By {r.customer.name ?? "—"} ({r.customer.email ?? "—"}) · shown publicly as “{r.author}”
        {r.created_at ? ` · ${new Date(r.created_at).toLocaleString("en-IN", { dateStyle: "medium", timeStyle: "short" })}` : ""}
      </p>
      <div className="flex flex-wrap gap-2">
        {r.status !== "approved" ? <Button size="sm" disabled={moderate.isPending} onClick={() => moderate.mutate("approved")}>Approve</Button> : null}
        {r.status !== "rejected" ? <Button size="sm" variant="outline" disabled={moderate.isPending} onClick={() => moderate.mutate("rejected")}>{r.status === "approved" ? "Unpublish" : "Reject"}</Button> : null}
      </div>
    </li>
  );
}
