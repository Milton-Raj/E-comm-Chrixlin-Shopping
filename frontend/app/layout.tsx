import type { Metadata, Viewport } from "next";
import localFont from "next/font/local";
import { preconnect } from "react-dom";
import { env } from "@/lib/env";
import { t } from "@/lib/i18n";
import { getSiteContent, siteText } from "@/features/catalog/site-content";
import { Providers } from "./providers";
import "./globals.css";

// Fonts are bundled with the app (Fontsource), not fetched from Google at build time:
// builds work on hosts without Google access, and visitors' browsers never call Google.
// Inter for all body/UI text (highly legible); the serif is reserved for large display headings.
// No italic files: the design never uses italics, and each preloaded font costs a request.
const sans = localFont({
  variable: "--font-sans",
  display: "swap",
  src: [
    { path: "../node_modules/@fontsource-variable/inter/files/inter-latin-wght-normal.woff2", weight: "100 900", style: "normal" },
  ],
});
const display = localFont({
  variable: "--font-display",
  display: "swap",
  src: [
    { path: "../node_modules/@fontsource/cormorant-garamond/files/cormorant-garamond-latin-400-normal.woff2", weight: "400", style: "normal" },
    { path: "../node_modules/@fontsource/cormorant-garamond/files/cormorant-garamond-latin-500-normal.woff2", weight: "500", style: "normal" },
    { path: "../node_modules/@fontsource/cormorant-garamond/files/cormorant-garamond-latin-600-normal.woff2", weight: "600", style: "normal" },
  ],
});
const geistMono = localFont({
  variable: "--font-geist-mono",
  display: "swap",
  // Only used for small codes (tracking numbers, references), so it isn't preloaded on every page.
  preload: false,
  src: [{ path: "../node_modules/@fontsource-variable/geist-mono/files/geist-mono-latin-wght-normal.woff2", weight: "100 900", style: "normal" }],
});

export async function generateMetadata(): Promise<Metadata> {
  const content = await getSiteContent();
  return {
    metadataBase: new URL(env.siteUrl),
    title: { default: `${env.storeName} · ${siteText(content, "brand.titleSuffix")}`, template: `%s · ${env.storeName}` },
    description: siteText(content, "brand.description"),
  };
}

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#0a0908",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  // Open the TLS connection to the API while the page loads; the cart and account calls
  // then skip a full round trip (the server is far from most shoppers).
  preconnect(new URL(env.apiUrl).origin, { crossOrigin: "use-credentials" });

  return (
    <html lang="en" className={`${sans.variable} ${display.variable} ${geistMono.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <a href="#main" className="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-background focus:px-4 focus:py-3 focus:shadow">
          {t("common.skipToContent")}
        </a>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
