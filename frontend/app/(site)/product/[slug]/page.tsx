import type { Metadata } from "next";
import { notFound, permanentRedirect } from "next/navigation";
import { Suspense } from "react";
import { LoadingState } from "@/components/states/loading-state";
import { productHref } from "@/features/catalog/catalog";
import { ProductDetail } from "@/features/catalog/components/product-detail";
import { getProduct, getProducts } from "@/features/catalog/server";

/** physical products live at /product/{slug} (PRD §50); the other path redirects to the canonical URL. */
export async function generateMetadata({ params }: PageProps<"/product/[slug]">): Promise<Metadata> {
  const product = await getProduct((await params).slug);
  if (!product) return {};
  return {
    title: product.seo.title,
    description: product.seo.description ?? undefined,
    alternates: { canonical: productHref(product) },
    openGraph: { title: product.name, description: product.short_description ?? undefined, images: product.images.map((i) => i.url) },
  };
}

export default function Page({ params }: PageProps<"/product/[slug]">) {
  return (
    <Suspense fallback={<div className="mx-auto max-w-7xl px-4 py-12 md:px-6"><LoadingState lines={8} /></div>}>
      {params.then(({ slug }) => <ProductContent slug={slug} />)}
    </Suspense>
  );
}

async function ProductContent({ slug }: { slug: string }) {
  const product = await getProduct(slug);
  if (!product) notFound();
  if (product.product_type !== "physical") permanentRedirect(productHref(product));

  const related = (await getProducts({ category: product.category?.slug, exclude: product.slug, per_page: 4 })).items;

  return <ProductDetail product={product} related={related} />;
}
