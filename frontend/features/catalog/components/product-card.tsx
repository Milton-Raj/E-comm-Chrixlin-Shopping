import Image from "next/image";
import Link from "next/link";
import { t } from "@/lib/i18n";
import { discountPercent, productHref } from "../catalog";
import type { Product } from "../types";
import { Price } from "./price";
import { Rating } from "./rating";
import { Reveal } from "@/components/motion/reveal";
import { WishlistButton } from "./wishlist-button";

/** Product card (PRD §57): image-led, quiet typography, badges kept to one line. */
/** `tone="dark"` for cards placed on rich-black sections. */
export function ProductCard({ product, preload = false, tone = "light" }: { product: Product; preload?: boolean; tone?: "light" | "dark" }) {
  const image = product.images[0];
  const muted = tone === "dark" ? "text-brand-grullo" : "text-muted-foreground";
  const discount = discountPercent(product);
  const href = productHref(product);
  const badge = discount
    ? t("catalog.off", { percent: discount })
    : product.is_new
      ? t("catalog.new")
      : product.product_type === "digital"
        ? t("catalog.digital")
        : null;

  return (
    <article className="group relative flex flex-col">
      <div className="relative aspect-4/5 overflow-hidden bg-muted">
        {image ? (
        <Image
          src={image.url}
          alt={image.alt}
          fill
          preload={preload}
          sizes="(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw"
          className="object-cover transition-transform duration-1000 ease-out group-hover:scale-106"
        />
        ) : null}
        {badge ? (
          <span className="eyebrow absolute top-3 left-3 bg-background/90 px-2.5 py-1 text-foreground">
            {badge}
          </span>
        ) : null}
        <div className="pointer-events-none absolute inset-0 bg-linear-to-t from-brand-black/25 to-transparent opacity-0 transition-opacity duration-700 group-hover:opacity-100" />
        <WishlistButton productUuid={product.uuid} name={product.name} className="absolute top-2 right-2 z-10" />
      </div>
      <div className="flex flex-1 flex-col gap-1 pt-4">
        {product.brand ? <p className={`eyebrow ${muted}`}>{product.brand.name}</p> : null}
        <h3 className="text-base leading-snug font-medium">
          <Link href={href} className="after:absolute after:inset-0 focus-visible:outline-none">
            <span className="link-draw">{product.name}</span>
          </Link>
        </h3>
        {/* Stars appear once a piece has reviews; an empty 0.0 rating reads as unpopular. */}
        {product.rating.count > 0 ? <Rating average={product.rating.average} count={product.rating.count} className={`hidden sm:flex ${tone === "dark" ? "text-brand-grullo" : ""}`} /> : null}
        <Price price={product.price} compareAt={product.compare_at_price} tone={tone} className="mt-1 text-sm font-medium" />
      </div>
    </article>
  );
}

export function ProductGrid({ products, preloadCount = 0 }: { products: Product[]; preloadCount?: number }) {
  return (
    <Reveal stagger key={products.map((p) => p.uuid).join()}>
    <ul className="grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 md:gap-x-6 xl:grid-cols-4">
      {products.map((product, i) => (
        <li key={product.uuid}>
          <ProductCard product={product} preload={i < preloadCount} />
        </li>
      ))}
    </ul>
    </Reveal>
  );
}
