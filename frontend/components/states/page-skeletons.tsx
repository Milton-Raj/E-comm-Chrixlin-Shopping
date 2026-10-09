import { Skeleton } from "@/components/ui/skeleton";
import { t } from "@/lib/i18n";

/** Instant route skeletons (loading.tsx): the outline of the next page while its data arrives. */

function Status() {
  return <span className="sr-only">{t("common.loading")}</span>;
}

export function ProductPageSkeleton() {
  return (
    <div role="status" aria-live="polite" className="mx-auto grid max-w-7xl gap-10 px-4 py-8 md:grid-cols-2 md:px-6 md:py-12">
      <Status />
      <Skeleton className="aspect-4/5 w-full" />
      <div className="grid content-start gap-4">
        <Skeleton className="h-4 w-24" />
        <Skeleton className="h-10 w-4/5" />
        <Skeleton className="h-7 w-32" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-5/6" />
        <Skeleton className="mt-4 h-12 w-full" />
        <Skeleton className="h-12 w-full" />
      </div>
    </div>
  );
}

export function ProductGridSkeleton({ title = true }: { title?: boolean }) {
  return (
    <div role="status" aria-live="polite" className="mx-auto grid max-w-7xl gap-8 px-4 py-10 md:px-6 md:py-14">
      <Status />
      {title ? <div className="grid justify-items-center gap-3"><Skeleton className="h-3 w-32" /><Skeleton className="h-12 w-48" /></div> : null}
      <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4" aria-hidden>
        {Array.from({ length: 8 }, (_, i) => (
          <li key={i} className="grid gap-3">
            <Skeleton className="aspect-4/5 w-full" />
            <Skeleton className="h-4 w-2/3" />
            <Skeleton className="h-4 w-1/3" />
          </li>
        ))}
      </ul>
    </div>
  );
}

export function TextPageSkeleton() {
  return (
    <div role="status" aria-live="polite" className="mx-auto grid max-w-3xl gap-4 px-4 py-12 md:px-6 md:py-16">
      <Status />
      <Skeleton className="mb-4 h-12 w-2/3" />
      {Array.from({ length: 8 }, (_, i) => <Skeleton key={i} className={i % 3 === 2 ? "h-4 w-3/4" : "h-4 w-full"} />)}
    </div>
  );
}

export function AdminPageSkeleton() {
  return (
    <div role="status" aria-live="polite" className="grid gap-6">
      <Status />
      <div className="grid gap-2"><Skeleton className="h-8 w-48" /><Skeleton className="h-4 w-80 max-w-full" /></div>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 4 }, (_, i) => <Skeleton key={i} className="h-24 w-full" />)}
      </div>
      <Skeleton className="h-72 w-full" />
    </div>
  );
}
