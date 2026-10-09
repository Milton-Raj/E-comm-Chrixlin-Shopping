import Link from "next/link";
import { t } from "@/lib/i18n";
import { getSiteContent, siteText } from "../site-content";
import { discountPercent } from "../catalog";
import type { Product, ProductDetail as ProductDetailType } from "../types";
import { Price } from "./price";
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
    product.stock_status === "low_stock" ? t("catalog.lowStock") : product.stock_status === "out_of_stock" ? t("catalog.outOfStock") : t("catalog.inStock");

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 md:px-6 md:py-12">
      <nav aria-label={t("catalog.breadcrumb")} className="eyebrow mb-8 text-muted-foreground">
        <Link href="/" className="hover:text-primary">{t("nav.home")}</Link> /{" "}
        {product.category ? (
          <>
            <Link href={`/category/${product.category.slug}`} className="hover:text-primary">{product.category.name}</Link> /{" "}
          </>
        ) : null}
        <span className="text-foreground">{product.name}</span>
      </nav>

      <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
        <ProductGallery images={product.images} name={product.name} />

        <div className="lg:sticky lg:top-36 lg:self-start">
          {product.brand ? <p className="eyebrow text-muted-foreground">{product.brand.name}</p> : null}
          <h1 className="font-display-tight mt-3 text-4xl md:text-5xl">{product.name}</h1>
          <Rating average={product.rating.average} count={product.rating.count} className="mt-4" />
          <div className="mt-6 flex items-center gap-3">
            <Price price={product.price} compareAt={product.compare_at_price} className="text-2xl" />
            {discount ? <span className="eyebrow bg-primary px-2.5 py-1 text-primary-foreground">{t("catalog.off", { percent: discount })}</span> : null}
          </div>
          <p className="mt-6 text-muted-foreground">{product.short_description}</p>
          {product.product_type === "physical" ? (
            <p className={product.stock_status === "low_stock" ? "mt-4 text-sm text-primary" : "mt-4 text-sm text-muted-foreground"}>{stockLabel}</p>
          ) : null}

          <div className="mt-8 border-t border-border pt-8">
            <PurchasePanel product={product} />
          </div>

          <div className="mt-8 grid gap-2 border-t border-border pt-6 text-sm">
            <p className="eyebrow">{siteText(content, "catalog.delivery")}</p>
            <p className="text-muted-foreground">
              {product.product_type === "digital" ? t("catalog.deliveryDigital") : siteText(content, "catalog.deliveryPhysical")}
              {product.product_type === "physical" && product.free_shipping ? <strong className="mt-1 block font-semibold text-foreground">{t("catalog.freeDelivery")}</strong> : null}
            </p>
          </div>
        </div>
      </div>

      <div className="mt-16 grid gap-12 border-t border-border pt-12 md:grid-cols-2 md:gap-16">
        <section aria-labelledby="desc-heading">
          <h2 id="desc-heading" className="font-display-tight text-3xl">{t("catalog.description")}</h2>
          <div className="mt-5 grid gap-4 leading-relaxed text-muted-foreground">
            {product.description.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
          </div>
        </section>
        <section aria-labelledby="spec-heading">
          <h2 id="spec-heading" className="font-display-tight text-3xl">{t("catalog.details")}</h2>
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

      {related.length ? (
        <section className="mt-20 md:mt-28">
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
