import { ProductEditorPage } from "@/features/admin/pages/product-editor";

/** Rendered after the client-side staff check (see admin/layout.tsx). */
export const instant = false;

export default function Page() {
  return <ProductEditorPage uuid={null} />;
}
