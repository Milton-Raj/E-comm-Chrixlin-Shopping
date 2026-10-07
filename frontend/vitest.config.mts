import { defineConfig } from "vitest/config";

export default defineConfig({
  resolve: { tsconfigPaths: true },
  test: {
    environment: "jsdom",
    globals: false,
    setupFiles: ["./vitest.setup.ts"],
    include: ["**/*.test.{ts,tsx}"],
    exclude: ["node_modules/**", ".next/**"],
    env: {
      NEXT_PUBLIC_API_URL: "http://api.test/api/v1",
      NEXT_PUBLIC_SITE_URL: "http://shop.test",
      NEXT_PUBLIC_STORE_NAME: "Test Store",
    },
  },
});
