import { t } from "@/lib/i18n";
import type { HeroSlide } from "./types";

/**
 * Built-in hero slides (photos in public/hero: AI-generated dessert candles and credited Pexels
 * Jesmonite photos, see public/demo/CREDITS.md). Shown until the
 * store adds its own slides in Admin → Content → Homepage hero.
 */
export function defaultHeroSlides(): HeroSlide[] {
  return [
    { key: "candles", image: "/hero/berry-cake.jpg", focal: "70% 50%", url: "/search?q=candle" },
    { key: "scent", image: "/hero/berry-trio.jpg", focal: "68% 50%", url: "/shop" },
    { key: "gift", image: "/hero/whipped-jar.jpg", focal: "66% 50%", url: "/search?q=jar" },
    { key: "jesmonite", image: "/hero/jesmonite-tray.jpg", focal: "50% 45%", url: "/search?q=jesmonite" },
  ].map(({ key, image, focal, url }) => {
    const k = key as "candles" | "jesmonite" | "scent" | "gift";
    return {
      uuid: `default-${k}`,
      image,
      image_alt: t(`hero.${k}.alt`),
      focal_point: focal,
      eyebrow: t(`hero.${k}.eyebrow`),
      title: t(`hero.${k}.title`),
      body: t(`hero.${k}.body`),
      cta: { label: t(`hero.${k}.cta`), url },
    };
  });
}
