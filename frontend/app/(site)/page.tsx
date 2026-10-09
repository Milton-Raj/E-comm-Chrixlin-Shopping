import { CreditCard, Download, Headset, RotateCcw, Truck } from "lucide-react";
import Image from "next/image";
import Link from "next/link";
import { ButtonLink } from "@/components/button-link";
import { ProductCard, ProductGrid } from "@/features/catalog/components/product-card";
import { SectionHeading } from "@/features/catalog/components/section-heading";
import { HeroSlider } from "@/features/catalog/components/hero-slider";
import { defaultHeroSlides } from "@/features/catalog/hero-defaults";
import { getCategories, getHeroSlides, getProducts } from "@/features/catalog/server";
import { Skeleton } from "@/components/ui/skeleton";
import { Suspense } from "react";
import { Reveal } from "@/components/motion/reveal";
import { t, type MessageKey } from "@/lib/i18n";

const values: { icon: typeof Truck; title: MessageKey; body: MessageKey }[] = [
  { icon: CreditCard, title: "home.valueSecure", body: "home.valueSecureBody" },
  { icon: Truck, title: "home.valueDelivery", body: "home.valueDeliveryBody" },
  { icon: Download, title: "home.valueInstant", body: "home.valueInstantBody" },
  { icon: RotateCcw, title: "home.valueReturns", body: "home.valueReturnsBody" },
  { icon: Headset, title: "home.valueSupport", body: "home.valueSupportBody" },
];

/** Homepage (PRD §10). Sections become CMS-configurable in Phase 4/7. */
export default function HomePage() {
  return (
    <>
      {/* 1 — Hero slideshow (Admin → Content → Homepage hero) */}
      <Suspense fallback={<HeroSlider slides={defaultHeroSlides().slice(0, 1)} />}>
        <Hero />
      </Suspense>

      {/* Promise ribbon */}
      <div className="overflow-hidden border-b border-brand-umber/40 bg-primary py-4 text-primary-foreground">
        <div className="animate-marquee flex w-max gap-12" aria-hidden>
          {[0, 1].map((copy) => (
            <ul key={copy} className="flex shrink-0 items-center gap-12">
              {[...values, ...values].map(({ title }, i) => (
                <li key={`${copy}-${i}`} className="eyebrow flex items-center gap-12 whitespace-nowrap">
                  {t(title)}
                  <span className="text-brand-grullo">✦</span>
                </li>
              ))}
            </ul>
          ))}
        </div>
      </div>

      {/* 2 — Categories */}
      <section className="mx-auto max-w-7xl px-4 pt-20 md:px-6 md:pt-28">
        <SectionHeading title={t("home.categoriesTitle")} href="/categories" />
        <Suspense fallback={<GridSkeleton count={7} />}>
          <CategoryStrip />
        </Suspense>
      </section>

      {/* 3 — New arrivals */}
      <section className="mx-auto max-w-7xl px-4 pt-20 md:px-6 md:pt-28">
        <SectionHeading eyebrow={t("catalog.new")} title={t("home.newArrivals")} href="/shop?sort=newest" />
        <Suspense fallback={<GridSkeleton count={4} />}>
          <ProductSection query={{ sort: "newest", type: "physical", per_page: 4 }} />
        </Suspense>
      </section>

      {/* 4 — Editorial banner */}
      <section className="mt-20 grid bg-primary text-primary-foreground md:mt-28 md:grid-cols-2">
        <Reveal variant="clip" className="relative min-h-80 overflow-hidden md:min-h-128">
          <Image src="/demo/editorial-home.jpg" alt="" fill sizes="(min-width: 768px) 50vw, 100vw" className="object-cover" />
        </Reveal>
        <Reveal delay={250} className="flex flex-col justify-center gap-6 px-6 py-16 md:px-16">
          <p className="eyebrow text-brand-grullo">{t("home.editorialEyebrow")}</p>
          <h2 className="font-display-tight text-4xl md:text-5xl">{t("home.editorialTitle")}</h2>
          <p className="max-w-md text-primary-foreground/80">{t("home.editorialBody")}</p>
          <div>
            <ButtonLink href="/categories" variant="outline-light" className="sheen">{t("home.editorialCta")}</ButtonLink>
          </div>
        </Reveal>
      </section>

      {/* 5 — Best sellers */}
      <section className="mx-auto max-w-7xl px-4 pt-20 md:px-6 md:pt-28">
        <SectionHeading eyebrow={t("catalog.bestSeller")} title={t("home.bestSellers")} href="/shop?sort=best_selling" />
        <Suspense fallback={<GridSkeleton count={8} />}>
          <ProductSection query={{ sort: "best_selling", type: "physical", per_page: 8 }} />
        </Suspense>
      </section>

      {/* 6 — Digital collection */}
      <section className="mt-20 bg-brand-black text-brand-cultured md:mt-28">
        <div className="mx-auto grid max-w-7xl gap-12 px-4 py-20 md:px-6 lg:grid-cols-3 lg:py-28">
          <Reveal className="flex flex-col justify-center gap-5">
            <p className="eyebrow text-brand-grullo">{t("home.digitalEyebrow")}</p>
            <h2 className="font-display-tight text-4xl md:text-5xl">{t("home.digitalTitle")}</h2>
            <p className="text-brand-cultured/75">{t("home.digitalBody")}</p>
            <div>
              <ButtonLink href="/shop?type=digital" variant="light" className="sheen">{t("catalog.viewAll")}</ButtonLink>
            </div>
          </Reveal>
          <div className="lg:col-span-2">
            <Suspense fallback={<GridSkeleton count={3} />}>
              <DigitalSection />
            </Suspense>
          </div>
        </div>
      </section>

      {/* 9 — Why shop with us */}
      <section className="mx-auto max-w-7xl px-4 pt-20 md:px-6 md:pt-28">
        <Reveal stagger>
        <ul className="grid grid-cols-2 gap-8 border-y border-border py-12 md:grid-cols-5">
          {values.map(({ icon: Icon, title, body }) => (
            <li key={title} className="group flex flex-col items-center gap-3 text-center">
              <span className="flex size-12 items-center justify-center rounded-full border border-border transition-all duration-500 group-hover:-translate-y-1 group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground">
                <Icon className="size-5 text-primary transition-colors duration-500 group-hover:text-primary-foreground" aria-hidden />
              </span>
              <p className="eyebrow">{t(title)}</p>
              <p className="text-sm text-muted-foreground">{t(body)}</p>
            </li>
          ))}
        </ul>
        </Reveal>
      </section>
    </>
  );
}

async function Hero() {
  const slides = await getHeroSlides();
  return <HeroSlider slides={slides.length ? slides : defaultHeroSlides()} />;
}

async function CategoryStrip() {
  const categories = await getCategories();

  return (
    <Reveal stagger>
    <ul className="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-2 md:mx-0 md:grid md:grid-cols-4 md:overflow-visible md:px-0 lg:grid-cols-7">
      {categories.map((c) => (
        <li key={c.slug} className="w-40 shrink-0 snap-start md:w-auto">
          <Link href={`/category/${c.slug}`} className="group block">
            <div className="relative aspect-3/4 overflow-hidden bg-muted">
              {c.image ? <Image src={c.image} alt="" fill sizes="(min-width: 1024px) 14vw, (min-width: 768px) 25vw, 160px" className="object-cover transition-transform duration-1000 ease-out group-hover:scale-108" /> : null}
              <div className="absolute inset-0 bg-brand-black/0 transition-colors duration-700 group-hover:bg-brand-black/15" />
            </div>
            <p className="eyebrow mt-3 transition-colors duration-300 group-hover:text-primary">{c.name}</p>
          </Link>
        </li>
      ))}
    </ul>
    </Reveal>
  );
}

async function ProductSection({ query }: { query: Parameters<typeof getProducts>[0] }) {
  const { items } = await getProducts(query);
  return <ProductGrid products={items} />;
}

async function DigitalSection() {
  const { items } = await getProducts({ type: "digital", sort: "featured", per_page: 3 });

  return (
    <Reveal stagger>
    <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3">
      {items.map((p) => (
        <li key={p.uuid}>
          <ProductCard product={p} tone="dark" />
        </li>
      ))}
    </ul>
    </Reveal>
  );
}

function GridSkeleton({ count }: { count: number }) {
  return (
    <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4" aria-hidden>
      {Array.from({ length: count }, (_, i) => (
        <li key={i} className="grid gap-3">
          <Skeleton className="aspect-4/5 w-full" />
          <Skeleton className="h-4 w-2/3" />
        </li>
      ))}
    </ul>
  );
}
