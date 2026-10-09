import { t } from "@/lib/i18n";
import type { HeroSlide } from "./types";

/**
 * Built-in hero slides (photos in public/hero, see public/demo/CREDITS.md). Shown until the
 * store adds its own slides in Admin → Content → Homepage hero.
 */
export function defaultHeroSlides(): HeroSlide[] {
  return [
    { key: "candles", image: "/hero/candles-evening.jpg", focal: "50% 75%", url: "/search?q=candle" },
    { key: "jesmonite", image: "/hero/jesmonite-tray.jpg", focal: "50% 45%", url: "/search?q=jesmonite" },
    { key: "scent", image: "/hero/amber-candle.jpg", focal: "40% 50%", url: "/search?q=candle" },
    { key: "gift", image: "/hero/arch-candle.jpg", focal: "60% 50%", url: "/shop" },
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
