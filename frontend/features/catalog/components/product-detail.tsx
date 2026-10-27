import Link from "next/link";
import { t } from "@/lib/i18n";
import { getSiteContent, siteText } from "../site-content";
import { discountPercent } from "../catalog";
import type { Product, ProductDetail as ProductDetailType } from "../types";
import { Price } from "./price";
import { ProductAssurance, ProductShareLinks } from "./product-assurance";
import { ProductReviews } from "@/features/reviews/components/product-reviews";
import { ProductGrid } from "./product-card";
import { ProductGallery } from "./product-gallery";
import { PurchasePanel } from "./purchase-panel";
import { Rating } from "./rating";
import { SectionHeading } from "./section-heading";

/** Product detail page body (PRD §58), shared by /product and /digital routes. */
export async function ProductDetail({ product, related }: { product: ProductDetailType; related: Product[] }) {
  const content = await getSiteContent();
  const discount = discountPercent(product);
  const stockLabel =
    product.stock_status === "low_stock" ? (product.low_stock_count ? t("catalog.onlyLeft", { count: product.low_stock_count }) : t("catalog.lowStock")) : product.stock_status === "out_of_stock" ? t("catalog.outOfStock") : t("catalog.inStock");

  return (
    <div className="mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-6">
      <nav aria-label={t("catalog.breadcrumb")} className="eyebrow mb-5 hidden truncate text-muted-foreground md:block">
        <Link href="/" className="hover:text-primary">{t("nav.home")}</Link> /{" "}
        {product.category ? (
          <>
            <Link href={`/category/${product.category.slug}`} className="hover:text-primary">{product.category.name}</Link> /{" "}
          </>
        ) : null}
        <span className="text-foreground">{product.name}</span>
      </nav>

      {/* Above the fold on desktop: the whole photo/video and everything down to "Made for you".
          On phones the purchase buttons pin above the bottom navigation instead (PurchasePanel). */}
      <div className="grid gap-6 lg:flex lg:items-start lg:gap-14">
        <div className="min-w-0 lg:shrink-0">
          <ProductGallery images={product.images} videos={product.videos ?? []} name={product.name} />
        </div>

        <div className="min-w-0 lg:sticky lg:top-36 lg:flex-1">
          {product.brand ? <p className="eyebrow text-muted-foreground">{product.brand.name}</p> : null}
          <h1 className="font-display-tight mt-2 text-3xl md:text-4xl">{product.name}</h1>
          <div className="mt-1 flex flex-wrap items-center justify-between gap-x-6">
            {product.rating.count > 0 ? <Rating average={product.rating.average} count={product.rating.count} /> : <span />}
            <ProductShareLinks product={product} content={content} className="text-muted-foreground" />
          </div>
          <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
            <Price price={product.price} compareAt={product.compare_at_price} className="text-2xl" />
            {discount ? <span className="eyebrow bg-primary px-2.5 py-1 text-primary-foreground">{t("catalog.off", { percent: discount })}</span> : null}
            {product.product_type === "physical" ? (
              <span className={product.stock_status === "low_stock" ? "text-sm text-primary" : "text-sm text-muted-foreground"}>· {stockLabel}</span>
            ) : null}
          </div>
          <p className="mt-3 text-muted-foreground">{product.short_description}</p>

          <div className="mt-4 border-t border-border pt-4">
            <PurchasePanel product={product} />
            <ProductAssurance product={product} content={content} />
          </div>

          <div className="mt-4 grid gap-1 border-t border-border pt-4 text-sm">
            <p className="eyebrow">{siteText(content, "catalog.delivery")}</p>
            <p className="text-muted-foreground">
              {product.product_type === "digital" ? t("catalog.deliveryDigital") : siteText(content, "catalog.deliveryPhysical")}
              {product.product_type === "physical" && product.free_shipping ? <strong className="mt-1 block font-semibold text-foreground">{t("catalog.freeDelivery")}</strong> : null}
            </p>
          </div>
        </div>
      </div>

      {/* Sections below the fold share one rhythm: a hairline, then the same space above and below it. */}
      <div className="mt-12 grid gap-10 border-t border-border pt-10 md:mt-16 md:grid-cols-2 md:gap-16 md:pt-14">
        <section aria-labelledby="desc-heading">
          <h2 id="desc-heading" className="font-display-tight text-3xl md:text-4xl">{t("catalog.description")}</h2>
          <div className="mt-5 grid max-w-prose gap-4 leading-relaxed text-muted-foreground">
            {product.description.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
          </div>
        </section>
        <section aria-labelledby="spec-heading">
          <h2 id="spec-heading" className="font-display-tight text-3xl md:text-4xl">{t("catalog.details")}</h2>
          <dl className="mt-5 divide-y divide-border border-y border-border text-sm">
            {product.digital ? (
              <>
                {product.digital.format ? <Spec label={t("catalog.digitalFormat")} value={product.digital.format} /> : null}
                {product.digital.file_size ? <Spec label={t("catalog.digitalSize")} value={product.digital.file_size} /> : null}
                {product.digital.download_limit ? <Spec label={t("catalog.digitalDownloads")} value={t("catalog.digitalDownloadsValue", { count: product.digital.download_limit })} /> : null}
                <Spec
                  label={t("catalog.digitalAccess")}
                  value={product.digital.access_days ? t("catalog.digitalAccessDays", { days: product.digital.access_days }) : t("catalog.digitalAccessLifetime")}
                />
              </>
            ) : null}
            {product.specifications.map((s) => <Spec key={s.label} label={s.label} value={s.value} />)}
          </dl>
        </section>
      </div>

      <ProductReviews slug={product.slug} productName={product.name} />

      {related.length ? (
        <section className="mt-12 border-t border-border pt-10 md:mt-16 md:pt-14">
          <SectionHeading title={t("catalog.related")} href={product.category ? `/category/${product.category.slug}` : "/shop"} />
          <ProductGrid products={related} />
        </section>
      ) : null}
    </div>
  );
}

function Spec({ label, value }: { label: string; value: string }) {
  return (
    <div className="grid grid-cols-5 gap-4 py-3">
      <dt className="col-span-2 text-muted-foreground">{label}</dt>
      <dd className="col-span-3">{value}</dd>
    </div>
  );
}
