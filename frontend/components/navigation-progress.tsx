"use client";

import { usePathname, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

/**
 * A thin bar across the top that starts the moment an internal link is clicked and
 * finishes when the new page is on screen. On slow connections (the server is far from
 * most shoppers) this is the difference between "it's loading" and "my click didn't work".
 */
export function NavigationProgress() {
  const pathname = usePathname();
  const search = useSearchParams().toString();
  const [state, setState] = useState<"idle" | "loading" | "done">("idle");
  const route = `${pathname}?${search}`;
  const [lastRoute, setLastRoute] = useState(route);

  // Finish when the route actually changes (state adjusted during render, React's recommended pattern).
  if (lastRoute !== route) {
    setLastRoute(route);
    if (state === "loading") setState("done");
  }

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const link = (e.target as Element | null)?.closest?.("a[href]");
      if (!(link instanceof HTMLAnchorElement) || link.target === "_blank" || link.hasAttribute("download")) return;
      const url = new URL(link.href, window.location.href);
      if (url.origin !== window.location.origin) return;
      if (url.pathname === window.location.pathname && url.search === window.location.search) return;
      setState("loading");
    };
    document.addEventListener("click", onClick, true);
    return () => document.removeEventListener("click", onClick, true);
  }, []);

  useEffect(() => {
    if (state !== "done") return;
    const timer = window.setTimeout(() => setState("idle"), 400);
    return () => window.clearTimeout(timer);
  }, [state]);

  // Safety net: never leave the bar stuck if a navigation is cancelled.
  useEffect(() => {
    if (state !== "loading") return;
    const timer = window.setTimeout(() => setState("done"), 15000);
    return () => window.clearTimeout(timer);
  }, [state]);

  if (state === "idle") return null;
  return (
    <div className="pointer-events-none fixed inset-x-0 top-0 z-100 h-0.5" role="progressbar" aria-label="Loading page" aria-busy={state === "loading"}>
      <div className={state === "loading" ? "nav-progress-run h-full bg-primary" : "nav-progress-done h-full bg-primary"} />
    </div>
  );
}
