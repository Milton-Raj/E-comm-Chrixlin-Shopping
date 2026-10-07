"use client";

import { useEffect, useRef, type ReactNode } from "react";

/**
 * Moves its content slower than the page while it is on screen (hero imagery).
 * One passive scroll listener, batched with requestAnimationFrame; off for reduced motion.
 */
export function Parallax({ children, speed = 0.25, className }: { children: ReactNode; speed?: number; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    let frame = 0;
    const update = () => {
      frame = 0;
      const y = Math.min(window.scrollY, window.innerHeight * 1.2);
      el.style.transform = `translate3d(0, ${(y * speed).toFixed(1)}px, 0)`;
    };
    const onScroll = () => { if (!frame) frame = requestAnimationFrame(update); };
    update();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => { window.removeEventListener("scroll", onScroll); if (frame) cancelAnimationFrame(frame); };
  }, [speed]);

  return <div ref={ref} className={className} style={{ willChange: "transform" }}>{children}</div>;
}
