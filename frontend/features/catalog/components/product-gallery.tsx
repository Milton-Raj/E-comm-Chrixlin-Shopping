"use client";

import { ChevronLeft, ChevronRight, Play } from "lucide-react";
import Image from "next/image";
import { useRef, useState, type KeyboardEvent, type PointerEvent } from "react";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import type { ProductImage, ProductVideo } from "../types";

type Slide = { kind: "image"; image: ProductImage } | { kind: "video"; video: ProductVideo };

const reducedMotion = () => typeof window !== "undefined" && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

/**
 * Product photos and optional YouTube videos in one swipeable strip (PRD §59): swipe or
 * scroll on phones, arrows and thumbnails everywhere, hover zoom on photos with a mouse.
 * A video shows its thumbnail until tapped; only then does the YouTube player load
 * (privacy-enhanced youtube-nocookie.com), and it stops when the shopper moves on.
 */
export function ProductGallery({ images, videos = [], name }: { images: ProductImage[]; videos?: ProductVideo[]; name: string }) {
  const slides: Slide[] = [...images.map((image) => ({ kind: "image" as const, image })), ...videos.map((video) => ({ kind: "video" as const, video }))];
  const track = useRef<HTMLDivElement>(null);
  const [active, setActive] = useState(0);
  const [playing, setPlaying] = useState<string | null>(null);
  const [origin, setOrigin] = useState<string | null>(null);
  const many = slides.length > 1;

  const goTo = (index: number) => {
    const el = track.current;
    if (!el) return;
    const next = Math.max(0, Math.min(slides.length - 1, index));
    el.scrollTo({ left: next * el.clientWidth, behavior: reducedMotion() ? "auto" : "smooth" });
  };

  const onScroll = () => {
    const el = track.current;
    if (!el || !el.clientWidth) return;
    const index = Math.round(el.scrollLeft / el.clientWidth);
    if (index !== active) {
      setActive(index);
      setOrigin(null);
      setPlaying(null); // leaving a video stops it
    }
  };

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (event.key === "ArrowRight") { event.preventDefault(); goTo(active + 1); }
    if (event.key === "ArrowLeft") { event.preventDefault(); goTo(active - 1); }
  };

  const onMove = (event: PointerEvent<HTMLDivElement>) => {
    if (event.pointerType !== "mouse") return;
    const rect = event.currentTarget.getBoundingClientRect();
    setOrigin(`${((event.clientX - rect.left) / rect.width) * 100}% ${((event.clientY - rect.top) / rect.height) * 100}%`);
  };

  if (!slides.length) return <div className="aspect-4/5 bg-muted" />;

  return (
    // Phones: square photo, thumbnails below. Desktop: a 4:5 photo sized to the viewport height
    // (pdp-media) so the whole picture is visible on arrival, thumbnails in a slim column on its left.
    <div className="flex min-w-0 flex-col gap-3 lg:flex-row-reverse lg:items-start">
      {/* The wrapper owns the photo's shape so arrows and the counter sit exactly on it. */}
      <div className="group relative aspect-square bg-muted lg:aspect-4/5 lg:pdp-media lg:shrink-0">
        <div
          ref={track}
          onScroll={onScroll}
          onKeyDown={many ? onKeyDown : undefined}
          tabIndex={many ? 0 : undefined}
          role="region"
          aria-roledescription="carousel"
          aria-label={t("gallery.label", { name })}
          className="absolute inset-0 flex snap-x snap-mandatory overflow-x-auto overscroll-x-contain scrollbar-none focus-visible:outline-2 focus-visible:outline-offset-2"
        >
          {slides.map((slide, i) => (
            <div
              key={slide.kind === "image" ? slide.image.url : slide.video.youtube_id}
              role="group"
              aria-roledescription="slide"
              aria-label={t("gallery.slide", { current: i + 1, total: slides.length })}
              className="relative h-full w-full shrink-0 snap-center overflow-hidden"
            >
              {slide.kind === "image" ? (
                <div className="absolute inset-0 md:cursor-zoom-in" onPointerMove={i === active ? onMove : undefined} onPointerLeave={() => setOrigin(null)}>
                  <Image
                    src={slide.image.url}
                    alt={slide.image.alt}
                    fill
                    preload={i === 0}
                    sizes="(min-width: 1024px) 45vw, 100vw"
                    className={cn("object-cover transition-transform duration-500 ease-out motion-reduce:transition-none", i === active && origin && "scale-175")}
                    style={i === active && origin ? { transformOrigin: origin } : undefined}
                  />
                </div>
              ) : (
                // The whole 16:9 video sits centred in the 4:5 frame, nothing cropped; the space
                // around it is a soft, darkened blur of the video's own thumbnail, not flat black.
                <div className="absolute inset-0 overflow-hidden bg-brand-black">
                  <Image src={slide.video.thumbnail} alt="" fill sizes="(min-width: 1024px) 45vw, 100vw" className="scale-125 object-cover opacity-50 blur-2xl" aria-hidden />
                  <div className="absolute inset-0 bg-brand-black/40" aria-hidden />
                  <div className="absolute inset-x-0 top-1/2 aspect-video -translate-y-1/2 bg-brand-black shadow-2xl">
                    {playing === slide.video.youtube_id ? (
                      <iframe
                        src={`https://www.youtube-nocookie.com/embed/${slide.video.youtube_id}?autoplay=1&rel=0&playsinline=1&modestbranding=1`}
                        title={t("gallery.videoTitle", { name })}
                        allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                        allowFullScreen
                        className="absolute inset-0 h-full w-full"
                      />
                    ) : (
                      // hqdefault thumbnails are 4:3 with bars baked in; cover-cropping to 16:9 removes them.
                      <Image src={slide.video.thumbnail} alt="" fill sizes="(min-width: 1024px) 45vw, 100vw" className="object-cover" />
                    )}
                  </div>
                  {playing === slide.video.youtube_id ? null : (
                    <button type="button" onClick={() => setPlaying(slide.video.youtube_id)} className="group/video absolute inset-0 flex items-center justify-center" aria-label={t("gallery.playVideo", { name })}>
                      <span className="inline-flex size-16 items-center justify-center rounded-full bg-background/95 text-foreground shadow-lg ring-1 ring-background/40 transition-transform group-hover/video:scale-105 motion-reduce:transition-none">
                        <Play className="ml-1 size-6 fill-current" aria-hidden />
                      </span>
                    </button>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>

        {many ? (
          <>
            <button
              type="button"
              onClick={() => goTo(active - 1)}
              disabled={active === 0}
              aria-label={t("gallery.previous")}
              className="absolute top-1/2 left-2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-background/90 text-foreground shadow-sm transition-opacity hover:bg-background disabled:pointer-events-none disabled:opacity-0 md:opacity-0 md:group-hover:opacity-100 md:focus-visible:opacity-100"
            >
              <ChevronLeft className="size-5" aria-hidden />
            </button>
            <button
              type="button"
              onClick={() => goTo(active + 1)}
              disabled={active === slides.length - 1}
              aria-label={t("gallery.next")}
              className="absolute top-1/2 right-2 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-background/90 text-foreground shadow-sm transition-opacity hover:bg-background disabled:pointer-events-none disabled:opacity-0 md:opacity-0 md:group-hover:opacity-100 md:focus-visible:opacity-100"
            >
              <ChevronRight className="size-5" aria-hidden />
            </button>
            <p className="eyebrow pointer-events-none absolute right-2 bottom-2 bg-background/90 px-2 py-1 text-foreground" aria-live="polite">
              {t("gallery.slide", { current: active + 1, total: slides.length })}
            </p>
          </>
        ) : null}
      </div>

      {many ? (
        <ul className="flex gap-2 overflow-x-auto scrollbar-none lg:pdp-media-max lg:w-16 lg:shrink-0 lg:flex-col lg:overflow-x-visible lg:overflow-y-auto" aria-label={t("gallery.label", { name })}>
          {slides.map((slide, i) => (
            <li key={slide.kind === "image" ? slide.image.url : slide.video.youtube_id} className="w-12 shrink-0 lg:w-16">
              <button
                type="button"
                onClick={() => goTo(i)}
                aria-current={i === active}
                aria-label={t("gallery.show", { number: i + 1 })}
                className={cn("relative block aspect-square w-full overflow-hidden border bg-muted", i === active ? "border-foreground" : "border-transparent")}
              >
                <Image src={slide.kind === "image" ? slide.image.url : slide.video.thumbnail} alt="" fill sizes="96px" className="object-cover" />
                {slide.kind === "video" ? (
                  <span className="absolute inset-0 flex items-center justify-center bg-foreground/25">
                    <span className="inline-flex size-7 items-center justify-center rounded-full bg-background/95"><Play className="ml-0.5 size-3.5 fill-current" aria-hidden /></span>
                  </span>
                ) : null}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
