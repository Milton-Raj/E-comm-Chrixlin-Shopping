import { EmptyState } from "@/components/states/empty-state";
import { getPage } from "@/features/catalog/server";

/** Renders a CMS page as escaped paragraphs (no HTML from the database is ever injected). */
export async function ContentPageBody({ slug, fallbackTitle }: { slug: string; fallbackTitle: string }) {
  const page = await getPage(slug);

  if (!page) {
    return (
      <>
        <h1 className="font-display-tight mb-8 text-5xl">{fallbackTitle}</h1>
        <EmptyState title={fallbackTitle} description="This page is being prepared and will be published soon." />
      </>
    );
  }

  return (
    <article>
      <h1 className="font-display-tight mb-8 text-5xl">{page.title}</h1>
      <div className="grid gap-5 leading-relaxed text-muted-foreground">
        {page.paragraphs.map((paragraph, i) => (
          <p key={i} className="whitespace-pre-line">{paragraph}</p>
        ))}
      </div>
      <p className="mt-10 text-xs text-muted-foreground">Last updated {new Date(page.updated_at).toLocaleDateString("en-IN", { dateStyle: "long" })}</p>
    </article>
  );
}
