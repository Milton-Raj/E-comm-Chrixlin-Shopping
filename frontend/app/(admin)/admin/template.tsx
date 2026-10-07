import type { ReactNode } from "react";

/** Admin pages fade in briefly on navigation: noticeable, never in the way. */
export default function AdminTemplate({ children }: { children: ReactNode }) {
  return <div className="animate-page-quick">{children}</div>;
}
