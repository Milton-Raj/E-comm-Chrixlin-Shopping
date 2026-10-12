import { describe, expect, it } from "vitest";
import { applyPercentOff, fromMinorUnits, percentOff, percentToBasisPoints, toMinorUnits } from "./money-input";

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

describe("percentage discounts", () => {
  it("parses percentages to basis points", () => {
    expect(percentToBasisPoints("20")).toBe(2000);
    expect(percentToBasisPoints("12.5")).toBe(1250);
    expect(percentToBasisPoints(" 7.25 %")).toBe(725);
    expect(percentToBasisPoints("")).toBe(0);
    expect(percentToBasisPoints("100")).toBeNull();
    expect(percentToBasisPoints("1.234")).toBeNull();
    expect(percentToBasisPoints("-5")).toBeNull();
  });

  it("applies the discount in whole minor units", () => {
    expect(applyPercentOff(100000, 2000)).toBe(80000);
    expect(applyPercentOff(99900, 3300)).toBe(66933);
    expect(applyPercentOff(44000, 1250)).toBe(38500);
    expect(applyPercentOff(50000, 0)).toBe(50000);
  });

  it("derives the percentage back from stored prices", () => {
    expect(percentOff(80000, 100000)).toBe("20");
    expect(percentOff(38500, 44000)).toBe("12.5");
    expect(percentOff(50000, null)).toBe("");
    expect(percentOff(50000, 40000)).toBe("");
  });
});
