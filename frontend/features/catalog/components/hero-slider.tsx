"use client";

import { ChevronLeft, ChevronRight, Pause, Play } from "lucide-react";
import Image from "next/image";
import { Fragment, useCallback, useEffect, useRef, useState, type CSSProperties } from "react";
import { ButtonLink } from "@/components/button-link";
import { Parallax } from "@/components/motion/parallax";
import { t } from "@/lib/i18n";
import { cn } from "@/lib/utils";
import type { HeroSlide } from "../types";

const DURATION_MS = 6500;
const delay = (ms: number) => ({ "--delay": `${ms}ms` }) as CSSProperties;

/**
 * Full-bleed homepage slideshow: crossfading photos with a slow zoom, one message per slide.
 * Auto-advances everywhere, pausing only for the pause button (WCAG 2.2.2), keyboard focus
 * inside the slideshow, or a hidden tab; never auto-advances for reduced motion. A resting mouse
 * does not pause it (on desktop the cursor often sits over the hero). Swipe and arrow keys work too.
 */
export function HeroSlider({ slides }: { slides: HeroSlide[] }) {
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);
  const [held, setHeld] = useState(false);
  const [reduced, setReduced] = useState(false);
  const [hidden, setHidden] = useState(false);
  const touchX = useRef<number | null>(null);
  const count = slides.length;
  const playing = count > 1 && !paused && !held && !reduced && !hidden;

  const go = useCallback((next: number) => setIndex(((next % count) + count) % count), [count]);

  useEffect(() => {
    const media = window.matchMedia("(prefers-reduced-motion: reduce)");
    const sync = () => setReduced(media.matches);
    const visibility = () => setHidden(document.hidden);
    sync();
    media.addEventListener("change", sync);
    document.addEventListener("visibilitychange", visibility);
    return () => { media.removeEventListener("change", sync); document.removeEventListener("visibilitychange", visibility); };
  }, []);

  useEffect(() => {
    if (!playing) return;
    const timer = window.setTimeout(() => go(index + 1), DURATION_MS);
    return () => window.clearTimeout(timer);
  }, [playing, index, go]);

  if (count === 0) return null;
  const slide = slides[index]!;

  return (
    <section
      aria-roledescription="carousel"
      aria-label={t("hero.label")}
      className="relative isolate hero-stage flex items-end overflow-hidden bg-brand-black text-brand-cultured"
      onFocus={(e) => { if (e.target.matches(":focus-visible")) setHeld(true); }}
      onBlur={(e) => { if (!e.currentTarget.contains(e.relatedTarget as Node | null)) setHeld(false); }}
      onKeyDown={(e) => {
        if (e.key === "ArrowRight") go(index + 1);
        if (e.key === "ArrowLeft") go(index - 1);
      }}
      onTouchStart={(e) => { touchX.current = e.touches[0]?.clientX ?? null; }}
      onTouchEnd={(e) => {
        const start = touchX.current;
        const end = e.changedTouches[0]?.clientX;
        touchX.current = null;
        if (start !== null && end !== undefined && Math.abs(end - start) > 50) go(index + (end < start ? 1 : -1));
      }}
    >
      <h1 className="sr-only">{t("home.srTitle")}</h1>

      <Parallax className="absolute inset-0 -z-10">
        {slides.map((s, i) => (
          <div key={s.uuid} className="hero-slide absolute inset-0" data-active={i === index ? "" : undefined} aria-hidden={i !== index}>
            <Image
              src={s.image}
              alt={s.image_alt ?? ""}
              fill
              preload={i === 0}
              loading={i === 0 ? undefined : "lazy"}
              sizes="100vw"
              className="object-cover"
              style={{ objectPosition: s.focal_point }}
            />
          </div>
        ))}
      </Parallax>
      <div className="absolute inset-0 -z-10 bg-linear-to-t from-brand-black/95 via-brand-black/50 to-brand-black/10" />
      <div className="absolute inset-0 -z-10 hidden bg-linear-to-r from-brand-black/60 to-transparent md:block" />

      <div className="mx-auto w-full max-w-7xl px-4 pt-32 pb-28 md:px-6 md:pb-32">
        <div key={slide.uuid} role="group" aria-roledescription="slide" aria-label={t("hero.slide", { n: index + 1, total: count })}
          aria-live={playing ? "off" : "polite"}>
          {slide.eyebrow ? <p className="eyebrow animate-rise mb-5 text-brand-grullo" style={delay(100)}>{slide.eyebrow}</p> : null}
          <h2 className="font-display-tight max-w-3xl text-5xl text-balance md:text-7xl lg:text-8xl">
            {slide.title.split(" ").map((word, i, words) => (
              <Fragment key={i}>
                <span className="word-mask"><span className="animate-word" style={delay(200 + i * 90)}>{word}</span></span>
                {i < words.length - 1 ? " " : null}
              </Fragment>
            ))}
          </h2>
          {slide.body ? <p className="animate-rise mt-6 max-w-lg text-lg text-brand-cultured/85" style={delay(650)}>{slide.body}</p> : null}
          <div className="animate-rise mt-10 flex flex-wrap gap-3" style={delay(850)}>
            {slide.cta ? <ButtonLink href={slide.cta.url} size="lg" variant="light" className="sheen">{slide.cta.label}</ButtonLink> : null}
            <ButtonLink href="/shop" size="lg" variant="outline-light" className="sheen">{t("hero.shopAll")}</ButtonLink>
          </div>
        </div>
      </div>

      {count > 1 ? (
        <div className="absolute inset-x-0 bottom-0">
          <div className="mx-auto flex w-full max-w-7xl items-center gap-4 px-4 pb-6 md:px-6 md:pb-8">
            <ol className="flex flex-1 items-center gap-2" aria-label={t("hero.label")}>
              {slides.map((s, i) => (
                <li key={s.uuid} className="flex-1 md:max-w-24">
                  <button type="button" onClick={() => go(i)} aria-label={t("hero.goTo", { n: i + 1 })} aria-current={i === index ? "true" : undefined}
                    className="group flex h-11 w-full items-center">
                    <span className="relative block h-0.5 w-full overflow-hidden bg-brand-cultured/25 transition-colors group-hover:bg-brand-cultured/45">
                      {i < index ? <span className="absolute inset-0 bg-brand-cultured" /> : null}
                      {i === index ? (
                        <span key={`${index}-${playing}`} className={cn("hero-progress absolute inset-0 bg-brand-cultured", !playing && "hero-progress-paused")} />
                      ) : null}
                    </span>
                  </button>
                </li>
              ))}
            </ol>
            <span className="eyebrow hidden text-xs text-brand-cultured/70 tabular-nums sm:inline" aria-hidden>
              {String(index + 1).padStart(2, "0")} / {String(count).padStart(2, "0")}
            </span>
            <div className="flex items-center gap-1">
              <HeroControl label={t("hero.previous")} onClick={() => go(index - 1)} className="hidden md:inline-flex"><ChevronLeft className="size-5" aria-hidden /></HeroControl>
              <HeroControl label={paused ? t("hero.play") : t("hero.pause")} onClick={() => setPaused((p) => !p)}>
                {paused ? <Play className="size-4" aria-hidden /> : <Pause className="size-4" aria-hidden />}
              </HeroControl>
              <HeroControl label={t("hero.next")} onClick={() => go(index + 1)} className="hidden md:inline-flex"><ChevronRight className="size-5" aria-hidden /></HeroControl>
            </div>
          </div>
        </div>
      ) : null}
    </section>
  );
}

function HeroControl({ label, onClick, className, children }: { label: string; onClick: () => void; className?: string; children: React.ReactNode }) {
  return (
    <button type="button" aria-label={label} onClick={onClick}
      className={cn("inline-flex size-11 items-center justify-center rounded-full border border-brand-cultured/30 text-brand-cultured transition-colors hover:border-brand-cultured hover:bg-brand-cultured/10 focus-visible:ring-2 focus-visible:ring-brand-cultured focus-visible:outline-none", className)}>
      {children}
    </button>
  );
}
