import { PageEditor } from "@/features/admin/pages/content";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page() {
  return <PageEditor uuid={null} />;
}
