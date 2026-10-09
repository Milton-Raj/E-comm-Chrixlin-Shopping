import type { NextConfig } from "next";

const isDev = process.env.NODE_ENV !== "production";
const apiUrl = process.env.NEXT_PUBLIC_API_URL ? new URL(process.env.NEXT_PUBLIC_API_URL) : null;
const apiOrigin = apiUrl?.origin ?? "";
// Razorpay Checkout (loaded only when the gateway is enabled) needs its script, frame and API origins.
const razorpay = { script: "https://checkout.razorpay.com", frame: "https://api.razorpay.com https://checkout.razorpay.com", connect: "https://api.razorpay.com https://lumberjack.razorpay.com" };

/**
 * Static CSP (SECURITY.md §6, ARCHITECTURE D11). Nonce-based CSP would force
 * dynamic rendering and is incompatible with the prerendered static shell, so
 * inline scripts are allowed for now; hash-based SRI is evaluated in Phase 9.
 */
const contentSecurityPolicy = [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline' ${razorpay.script}${isDev ? " 'unsafe-eval'" : ""}`,
  "style-src 'self' 'unsafe-inline'",
  `img-src 'self' data: blob: ${apiOrigin}`.trim(),
  "font-src 'self' data:",
  `connect-src 'self' ${apiOrigin} ${razorpay.connect}${isDev ? " ws: http://localhost:*" : ""}`.trim(),
  `frame-src 'self' ${razorpay.frame}`,
  "frame-ancestors 'none'",
  "base-uri 'self'",
  "form-action 'self'",
  "object-src 'none'",
  ...(isDev ? [] : ["upgrade-insecure-requests"]),
].join("; ");

const securityHeaders = [
  { key: "Content-Security-Policy", value: contentSecurityPolicy },
  { key: "X-Content-Type-Options", value: "nosniff" },
  { key: "X-Frame-Options", value: "DENY" },
  { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
  { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
  ...(isDev ? [] : [{ key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" }]),
];

const nextConfig: NextConfig = {
  // Self-contained server bundle (.next/standalone) so the store can run on Hostinger's Node runtime.
  output: "standalone",
  cacheComponents: true,
  partialPrefetching: true,
  poweredByHeader: false,
  turbopack: {
    // Pin the root: a stray lockfile in a parent folder must not change module resolution.
    root: process.cwd(),
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
  images: {
    // Product media is served by the API host (public disk / CDN later).
    remotePatterns: apiUrl ? [{ protocol: apiUrl.protocol.replace(":", "") as "http" | "https", hostname: apiUrl.hostname, port: apiUrl.port, pathname: "/storage/**" }] : [],
    // Local development serves images from localhost, which the optimizer blocks by default.
    dangerouslyAllowLocalIP: isDev || apiUrl?.hostname === "localhost",
  },
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders }];
  },
};

export default nextConfig;
