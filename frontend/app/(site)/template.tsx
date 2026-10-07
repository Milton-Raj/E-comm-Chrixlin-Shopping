import type { ReactNode } from "react";

/** Re-mounts on every storefront navigation, so each page arrives with a soft rise. */
export default function SiteTemplate({ children }: { children: ReactNode }) {
  return <div className="animate-page">{children}</div>;
}
