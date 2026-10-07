import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { ContentPageBody } from "@/components/content/content-page";
import { LoadingState } from "@/components/states/loading-state";
import { getPage } from "@/features/catalog/server";

/** CMS pages managed in Admin → Content (PRD §47). Unknown slugs render the 404 state. */
export async function generateMetadata({ params }: PageProps<"/pages/[slug]">): Promise<Metadata> {
  const page = await getPage((await params).slug);
  return page ? { title: page.seo.title, description: page.seo.description ?? undefined } : {};
}

export default function ContentPage({ params }: PageProps<"/pages/[slug]">) {
  return (
    <div className="mx-auto max-w-3xl px-4 py-12 md:px-6 md:py-16">
      <Suspense fallback={<LoadingState lines={6} />}>
        {params.then(({ slug }) => <PageContent slug={slug} />)}
      </Suspense>
    </div>
  );
}

async function PageContent({ slug }: { slug: string }) {
  if (!(await getPage(slug))) notFound();
  return <ContentPageBody slug={slug} fallbackTitle={slug} />;
}
