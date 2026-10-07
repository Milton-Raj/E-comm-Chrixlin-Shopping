import { Reveal } from "@/components/motion/reveal";
import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { Suspense } from "react";
import { EmptyState } from "@/components/states/empty-state";
import { LoadingState } from "@/components/states/loading-state";
import { getCategories } from "@/features/catalog/server";
import { t } from "@/lib/i18n";

export const metadata: Metadata = { title: t("placeholder.categoriesTitle") };

export default function CategoriesPage() {
  return (
    <div className="mx-auto max-w-7xl px-4 py-12 md:px-6 md:py-16">
      <h1 className="font-display-tight mb-10 text-center text-5xl md:text-6xl">{t("placeholder.categoriesTitle")}</h1>
      <Suspense fallback={<LoadingState lines={6} />}>
        <CategoryGrid />
      </Suspense>
    </div>
  );
}

async function CategoryGrid() {
  const categories = await getCategories();
  if (!categories.length) return <EmptyState title={t("catalog.noResultsTitle")} />;

  return (
    <Reveal stagger>
    <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {categories.map((c, i) => (
        <li key={c.slug}>
          <Link href={`/category/${c.slug}`} className="group relative block aspect-4/5 overflow-hidden bg-brand-black text-brand-cultured">
            {c.image ? <Image src={c.image} alt="" fill preload={i < 3} sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" className="object-cover opacity-80 transition duration-1000 ease-out group-hover:scale-106 group-hover:opacity-65" /> : null}
            <div className="absolute inset-0 bg-linear-to-t from-brand-black/80 to-transparent" />
            <div className="absolute inset-x-0 bottom-0 p-6 transition-transform duration-700 ease-out group-hover:-translate-y-2">
              <h2 className="font-display-tight text-3xl">{c.name}</h2>
              {c.description ? <p className="mt-2 text-sm text-brand-cultured/80">{c.description}</p> : null}
              <p className="eyebrow mt-4 inline-flex items-center gap-2 border-b border-brand-cultured/60 pb-1">{t("catalog.shopCategory", { name: c.name })}<span aria-hidden className="transition-transform duration-500 group-hover:translate-x-1">→</span></p>
            </div>
          </Link>
        </li>
      ))}
    </ul>
    </Reveal>
  );
}
