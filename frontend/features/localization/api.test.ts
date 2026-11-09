import { describe, expect, it } from "vitest";
import { browserRegion } from "./api";

describe("browserRegion", () => {
  it("takes the region from the first language that has one", () => {
    expect(browserRegion(["en-US", "en"])).toBe("US");
    expect(browserRegion(["de", "de-DE"])).toBe("DE");
    expect(browserRegion(["zh-Hant-TW"])).toBe("TW");
    expect(browserRegion(["en"])).toBeNull();
    expect(browserRegion([])).toBeNull();
  });
});
