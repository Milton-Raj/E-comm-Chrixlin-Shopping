"use client";

import { useEffect, useRef, type CSSProperties, type ReactNode } from "react";

type RevealProps = {
  children: ReactNode;
  className?: string;
  /** "up" fades and rises; "clip" unveils like a curtain (for images). */
  variant?: "up" | "clip";
  /** Reveal the list items inside one after another instead of the block as a whole. */
  stagger?: boolean;
  delay?: number;
  as?: "div" | "section" | "header";
};

/**
 * Scroll-triggered reveal. The hidden starting state lives in CSS behind
 * `@media (scripting: enabled) and (prefers-reduced-motion: no-preference)`, so content is
 * always visible without JavaScript or with reduced motion. Reveals once, then stops observing.
 */
export function Reveal({ children, className, variant = "up", stagger = false, delay = 0, as: Tag = "div" }: RevealProps) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (typeof IntersectionObserver === "undefined") {
      el.dataset.revealed = "";
      return;
    }
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            (entry.target as HTMLElement).dataset.revealed = "";
            observer.unobserve(entry.target);
          }
        }
      },
      { rootMargin: "0px 0px -10% 0px", threshold: 0.12 },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <Tag
      ref={ref}
      className={className}
      data-reveal={variant}
      data-stagger={stagger ? "" : undefined}
      style={delay ? ({ "--reveal-delay": `${delay}ms` } as CSSProperties) : undefined}
    >
      {children}
    </Tag>
  );
}
