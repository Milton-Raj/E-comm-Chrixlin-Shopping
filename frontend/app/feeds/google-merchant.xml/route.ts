import { productHref } from "@/features/catalog/catalog";
import { getAllProducts } from "@/features/catalog/server";
import { absoluteUrl, productSummary } from "@/features/catalog/structured-data";
import type { Product } from "@/features/catalog/types";
import { env } from "@/lib/env";
import { moneyToDecimal } from "@/lib/money";

/**
 * Google Merchant Center product feed (RSS 2.0 + g: namespace) for free Shopping listings
 * and, later, Shopping ads. Register https://<site>/feeds/google-merchant.xml as a scheduled
 * fetch in Merchant Center. Physical products only: Merchant Center restricts digital goods.
 */

const escapeXml = (value: string) =>
  value.replace(/[<>&'"]/g, (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;", "'": "&apos;", '"': "&quot;" })[c] ?? c);

const tag = (name: string, value: string | null | undefined) => (value ? `<${name}>${escapeXml(value)}</${name}>` : "");

const price = (p: Product["price"]) => `${moneyToDecimal(p)} ${p.currency}`;

function item(product: Product): string {
  const onSale = product.compare_at_price && product.compare_at_price.amount > product.price.amount;
  const [image, ...more] = product.images;

  return [
    "<item>",
    tag("g:id", product.uuid),
    tag("g:title", product.name.slice(0, 150)),
    tag("g:description", productSummary(product).slice(0, 5000)),
    tag("g:link", absoluteUrl(productHref(product))),
    image ? tag("g:image_link", absoluteUrl(image.url)) : "",
    ...more.slice(0, 10).map((i) => tag("g:additional_image_link", absoluteUrl(i.url))),
    tag("g:availability", product.stock_status === "out_of_stock" ? "out_of_stock" : "in_stock"),
    // On sale: the regular price goes in g:price and the current one in g:sale_price.
    tag("g:price", price(onSale ? product.compare_at_price! : product.price)),
    onSale ? tag("g:sale_price", price(product.price)) : "",
    tag("g:brand", product.brand?.name ?? env.storeName),
    tag("g:condition", "new"),
    // Handmade goods have no barcode (GTIN) or manufacturer part number.
    tag("g:identifier_exists", "no"),
    tag("g:product_type", product.category?.name),
    "</item>",
  ].join("");
}

export async function GET(): Promise<Response> {
  const products = (await getAllProducts({ type: "physical" })).filter((p) => p.images.length > 0);

  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>',
    tag("title", env.storeName),
    tag("link", absoluteUrl("/")),
    tag("description", `${env.storeName} products`),
    ...products.map(item),
    "</channel></rss>",
  ].join("\n");

  return new Response(xml, { headers: { "Content-Type": "application/xml; charset=utf-8", "Cache-Control": "public, max-age=3600" } });
}
