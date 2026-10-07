import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { ShopBrowser } from "@/features/catalog/components/shop-browser";
import { getCategory } from "@/features/catalog/server";
import { t } from "@/lib/i18n";

export async function generateMetadata({ params }: PageProps<"/category/[slug]">): Promise<Metadata> {
  const category = await getCategory((await params).slug);
  return category ? { title: category.name, description: category.description ?? undefined } : {};
}

export default function CategoryPage({ params }: PageProps<"/category/[slug]">) {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <Suspense fallback={<LoadingState lines={6} />}>
        {params.then(({ slug }) => <CategoryContent slug={slug} />)}
      </Suspense>
    </div>
  );
}

async function CategoryContent({ slug }: { slug: string }) {
  const category = await getCategory(slug);
  if (!category) notFound();

  return (
    <>
      <nav aria-label={t("catalog.breadcrumb")} className="eyebrow mb-8 text-muted-foreground">
        <Link href="/" className="hover:text-primary">{t("nav.home")}</Link> / <Link href="/categories" className="hover:text-primary">{t("nav.categories")}</Link> / <span className="text-foreground">{category.name}</span>
      </nav>
      <header className="mb-10 text-center">
        <h1 className="font-display-tight text-5xl md:text-6xl">{category.name}</h1>
        {category.description ? <p className="mx-auto mt-4 max-w-xl text-muted-foreground">{category.description}</p> : null}
      </header>
      <Suspense fallback={<LoadingState lines={6} />}>
        <ShopBrowser fixedCategory={category.slug} />
      </Suspense>
    </>
  );
}
