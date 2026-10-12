"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { BadgeCheck, Star } from "lucide-react";
import Image from "next/image";
import { useState, useSyncExternalStore } from "react";
import { ErrorState } from "@/components/states/error-state";
import { Skeleton } from "@/components/ui/skeleton";
import { Button } from "@/components/ui/button";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import { reviewsApi, type PublicReview } from "../api";
import { ReviewForm } from "./review-form";

/** Approved customer reviews with photos, a rating summary and "Write a review" (PRD §37). */
const WRITE_HASH = "#write-review";
const subscribeHash = (onChange: () => void) => {
  window.addEventListener("hashchange", onChange);
  return () => window.removeEventListener("hashchange", onChange);
};

export function ProductReviews({ slug, productName }: { slug: string; productName: string }) {
  // Links from the review request email and My account → Orders end in #write-review and open the form.
  const linkedToForm = useSyncExternalStore(subscribeHash, () => window.location.hash === WRITE_HASH, () => false);
  const [chosen, setChosen] = useState<boolean | null>(null);
  const writing = chosen ?? linkedToForm;
  const setWriting = (open: boolean) => {
    setChosen(open);
    if (!open && window.location.hash === WRITE_HASH) window.history.replaceState(window.history.state, "", window.location.pathname + window.location.search);
  };
  const reviews = useInfiniteQuery({
    queryKey: ["reviews", slug],
    queryFn: ({ pageParam }) => reviewsApi.list(slug, pageParam),
    initialPageParam: 1,
    getNextPageParam: (last) => (last.pagination.page < last.pagination.last_page ? last.pagination.page + 1 : undefined),
  });
  const summary = reviews.data?.pages[0]?.summary;
  const items = reviews.data?.pages.flatMap((p) => p.reviews) ?? [];

  return (
    <section id="write-review" aria-labelledby="reviews-heading" className="mt-16 scroll-mt-24 border-t border-border pt-12">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h2 id="reviews-heading" className="font-display-tight text-3xl">{t("reviews.heading")}</h2>
        {!writing ? <Button variant="outline" onClick={() => setWriting(true)}>{t("reviews.write")}</Button> : null}
      </div>

      {writing ? <div className="mt-6"><ReviewForm slug={slug} productName={productName} onClose={() => setWriting(false)} /></div> : null}

      {reviews.isPending ? (
        <div className="mt-8 grid gap-4"><Skeleton className="h-20" /><Skeleton className="h-28" /><Skeleton className="h-28" /></div>
      ) : reviews.isError ? (
        <div className="mt-8"><ErrorState onRetry={() => void reviews.refetch()} /></div>
      ) : (
        <div className="mt-8 grid gap-10 md:grid-cols-3 md:gap-14">
          {summary ? <Summary summary={summary} /> : null}
          <div className="md:col-span-2">
            {items.length === 0 ? (
              <p className="text-muted-foreground">{t("reviews.empty")}</p>
            ) : (
              <ul className="grid gap-8">{items.map((r) => <ReviewItem key={r.uuid} review={r} />)}</ul>
            )}
            {reviews.hasNextPage ? (
              <Button variant="ghost" className="mt-6" onClick={() => void reviews.fetchNextPage()} disabled={reviews.isFetchingNextPage}>
                {reviews.isFetchingNextPage ? t("common.loading") : t("reviews.more")}
              </Button>
            ) : null}
          </div>
        </div>
      )}
    </section>
  );
}

function Stars({ value, className }: { value: number; className?: string }) {
  return (
    <span className={cn("flex", className)} role="img" aria-label={t("catalog.ratingLabel", { rating: value.toFixed(1) })}>
      {Array.from({ length: 5 }, (_, i) => <Star key={i} aria-hidden className={cn("size-4", i < Math.round(value) ? "fill-brand-grullo text-brand-grullo" : "text-border")} />)}
    </span>
  );
}

function Summary({ summary }: { summary: NonNullable<Awaited<ReturnType<typeof reviewsApi.list>>["summary"]> }) {
  const total = summary.count || 1;
  return (
    <div className="grid content-start gap-3">
      <p className="text-4xl font-semibold">{summary.count ? summary.average.toFixed(1) : "—"}</p>
      <Stars value={summary.average} />
      <p className="text-sm text-muted-foreground">{t("catalog.reviews", { count: summary.count })}</p>
      <dl className="mt-2 grid gap-1.5 text-sm">
        {(["5", "4", "3", "2", "1"] as const).map((star) => (
          <div key={star} className="flex items-center gap-2">
            <dt className="w-8 text-muted-foreground">{star}★</dt>
            <dd className="flex flex-1 items-center gap-2">
              <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"><span className="block h-full rounded-full bg-brand-grullo" style={{ width: `${(summary.histogram[star] / total) * 100}%` }} /></span>
              <span className="w-6 text-right text-xs text-muted-foreground">{summary.histogram[star]}</span>
            </dd>
          </div>
        ))}
      </dl>
    </div>
  );
}

function ReviewItem({ review: r }: { review: PublicReview }) {
  return (
    <li className="grid gap-2">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <Stars value={r.rating} />
        {r.title ? <p className="font-medium">{r.title}</p> : null}
      </div>
      <p className="text-sm text-muted-foreground">
        {r.author}
        {r.created_at ? ` · ${new Date(r.created_at).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" })}` : null}
        {r.verified ? <span className="ml-2 inline-flex items-center gap-1 text-primary"><BadgeCheck className="size-3.5" aria-hidden />{t("reviews.verified")}</span> : null}
      </p>
      <p className="leading-relaxed whitespace-pre-line">{r.body}</p>
      {r.photos.length ? (
        <ul className="mt-1 flex flex-wrap gap-2">
          {r.photos.map((p, i) => (
            <li key={p.url}>
              <a href={p.url} target="_blank" rel="noopener noreferrer" className="relative block size-24 overflow-hidden bg-muted" aria-label={t("reviews.photoLabel", { n: i + 1, author: r.author })}>
                <Image src={p.url} alt="" fill sizes="96px" className="object-cover" />
              </a>
            </li>
          ))}
        </ul>
      ) : null}
    </li>
  );
}
