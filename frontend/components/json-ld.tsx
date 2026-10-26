/**
 * schema.org structured data for search engines (ARCHITECTURE §8 SEO). Product text comes
 * from the admin, so "<" is escaped to keep a stray "</script>" from closing the tag.
 */
export function JsonLd({ data }: { data: Record<string, unknown> }) {
  return <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(data).replace(/</g, "\\u003c") }} />;
}
