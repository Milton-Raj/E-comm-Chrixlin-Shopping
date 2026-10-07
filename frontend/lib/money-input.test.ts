import { describe, expect, it } from "vitest";
import { fromMinorUnits, toMinorUnits } from "./money-input";

describe("money input", () => {
  it.each([
    ["2499", 249900],
    ["2,499.5", 249950],
    ["₹ 0.01", 1],
    ["19.99", 1999],
    ["", null],
    ["12.345", null],
    ["-5", null],
    ["abc", null],
  ])("toMinorUnits(%s) = %s", (input, expected) => {
    expect(toMinorUnits(input)).toBe(expected);
  });

  it("formats minor units without floating-point error", () => {
    expect(fromMinorUnits(1999)).toBe("19.99");
    expect(fromMinorUnits(5)).toBe("0.05");
    expect(fromMinorUnits(null)).toBe("");
  });
});
