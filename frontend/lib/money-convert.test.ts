import { describe, expect, it } from "vitest";
import { convertMoney } from "./money";

const inr = (amount: number) => ({ amount, currency: "INR" });
const usd = (rate: string) => ({ code: "USD", rate, exponent: 2 });

// Same cases as the API's PresentmentTest: catalogue prices must match the bag exactly.
describe("convertMoney", () => {
  it("converts exactly and rounds half-up like the API", () => {
    expect(convertMoney(inr(118_000), 2, usd("0.012"))).toEqual({ amount: 1_416, currency: "USD" });
    expect(convertMoney(inr(12_345), 2, usd("0.012")).amount).toBe(148);
    expect(convertMoney(inr(118_000), 2, usd("0.0123")).amount).toBe(1_451);
    expect(convertMoney(inr(100), 2, usd("0.0125")).amount).toBe(1);
    expect(convertMoney(inr(100), 2, usd("0.015")).amount).toBe(2);
  });

  it("respects a currency without minor units", () => {
    expect(convertMoney(inr(100_000), 2, { code: "JPY", rate: "1.83", exponent: 0 }).amount).toBe(1_830);
  });

  it("handles whole-number rates and large amounts", () => {
    expect(convertMoney(inr(1_000_000_000), 2, usd("190.123456789012")).amount).toBe(190_123_456_789);
    expect(convertMoney(inr(250), 2, { code: "INR", rate: "1", exponent: 2 }).amount).toBe(250);
  });
});
