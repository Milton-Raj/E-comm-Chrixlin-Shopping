import { describe, expect, it } from "vitest";
import { currentFyStart, fyLabel, fyRange, matchingFy, monthRange } from "./periods";

describe("report periods", () => {
  it("knows Indian financial years run April to March", () => {
    expect(fyRange(2026)).toEqual(["2026-04-01", "2027-03-31"]);
    expect(fyLabel(2026)).toBe("FY 2026–27");
    expect(currentFyStart(new Date(2027, 2, 31))).toBe(2026); // 31 Mar 2027
    expect(currentFyStart(new Date(2027, 3, 1))).toBe(2027);  // 1 Apr 2027
  });

  it("recognises a financial year range and month ends", () => {
    expect(matchingFy("2026-04-01", "2027-03-31")).toBe(2026);
    expect(matchingFy("2026-04-01", "2027-03-30")).toBeNull();
    expect(monthRange("2028-02")).toEqual(["2028-02-01", "2028-02-29"]);
  });
});
