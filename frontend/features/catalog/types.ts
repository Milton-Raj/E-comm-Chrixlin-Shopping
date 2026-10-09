import type { Money } from "@/types/api";

/**
 * Catalog shapes returned by the Laravel API (API.md §3.3, backend ProductResource).
 */
export type ProductType = "physical" | "digital";
export type StockStatus = "in_stock" | "low_stock" | "out_of_stock";

export type Category = {
  uuid: string;
  slug: string;
  name: string;
  description: string | null;
  image: string | null;
  product_count?: number;
};

export type ProductImage = { url: string; alt: string };
export type ProductOption = { name: string; values: string[] };

export type ProductVariant = {
  uuid: string;
  sku: string;
  name: string | null;
  options: Record<string, string>;
  price: Money;
  compare_at_price: Money | null;
  available: boolean;
};

export type DigitalDetails = {
  format: string | null;
  file_size: string | null;
  download_limit: number | null;
  access_days: number | null;
};

export type Product = {
  uuid: string;
  slug: string;
  name: string;
  product_type: ProductType;
  brand: { slug: string; name: string } | null;
  category: { slug: string; name: string } | null;
  short_description: string | null;
  price: Money;
  compare_at_price: Money | null;
  rating: { average: number; count: number };
  stock_status: StockStatus;
  is_new: boolean;
  is_best_seller: boolean;
  is_featured: boolean;
  images: ProductImage[];
};

export type ProductDetail = Product & {
  description: string[];
  options: ProductOption[];
  specifications: { label: string; value: string }[];
  variants: ProductVariant[];
  digital: DigitalDetails | null;
  seo: { title: string; description: string | null };
};

export type ProductSort = "featured" | "newest" | "price_asc" | "price_desc" | "rating" | "best_selling";

export type ProductQuery = {
  category?: string | null;
  type?: ProductType | null;
  q?: string | null;
  sort?: ProductSort | null;
  featured?: boolean;
  new?: boolean;
  best_seller?: boolean;
  exclude?: string | null;
  per_page?: number;
  page?: number;
};

export type Paginated<T> = {
  items: T[];
  pagination: { page: number; per_page: number; total: number; last_page: number };
};

export type ContentPage = {
  slug: string;
  title: string;
  paragraphs: string[];
  seo: { title: string; description: string | null };
  updated_at: string;
};

/** Homepage hero slide managed in Admin → Content (API.md §3.2). */
export type HeroSlide = {
  uuid: string;
  image: string;
  image_alt: string | null;
  focal_point: string;
  eyebrow: string | null;
  title: string;
  body: string | null;
  cta: { label: string; url: string } | null;
};
