"use client";

import type { Money } from "@/types/api";
import { useFormatMoney } from "../use-localization";

/** A price in the shopper's currency, for server-rendered pages. */
export function MoneyText({ money }: { money: Money }) {
  const format = useFormatMoney();
  return <>{format(money)}</>;
}
