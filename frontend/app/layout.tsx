import type { Metadata, Viewport } from "next";
import { Cormorant_Garamond, Geist_Mono, Inter } from "next/font/google";
import { env } from "@/lib/env";
import { t } from "@/lib/i18n";
import { Providers } from "./providers";
import "./globals.css";

// Inter for all body/UI text (highly legible); the serif is reserved for large display headings.
const sans = Inter({ variable: "--font-sans", subsets: ["latin"] });
const display = Cormorant_Garamond({ variable: "--font-display", subsets: ["latin"], weight: ["400", "500", "600"], style: ["normal", "italic"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });

export const metadata: Metadata = {
  metadataBase: new URL(env.siteUrl),
  title: { default: env.storeName, template: `%s · ${env.storeName}` },
  description: t("home.heroBody"),
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  themeColor: "#0a0908",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
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
