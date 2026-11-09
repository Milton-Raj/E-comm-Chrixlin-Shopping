/** ISO 3166-2:IN state/UT codes (GST uses them to decide CGST+SGST vs IGST). */
export const indianStates: { code: string; name: string }[] = [
  { code: "AN", name: "Andaman & Nicobar" }, { code: "AP", name: "Andhra Pradesh" }, { code: "AR", name: "Arunachal Pradesh" },
  { code: "AS", name: "Assam" }, { code: "BR", name: "Bihar" }, { code: "CH", name: "Chandigarh" }, { code: "CT", name: "Chhattisgarh" },
  { code: "DH", name: "Dadra & Nagar Haveli and Daman & Diu" }, { code: "DL", name: "Delhi" }, { code: "GA", name: "Goa" },
  { code: "GJ", name: "Gujarat" }, { code: "HR", name: "Haryana" }, { code: "HP", name: "Himachal Pradesh" }, { code: "JK", name: "Jammu & Kashmir" },
  { code: "JH", name: "Jharkhand" }, { code: "KA", name: "Karnataka" }, { code: "KL", name: "Kerala" }, { code: "LA", name: "Ladakh" },
  { code: "LD", name: "Lakshadweep" }, { code: "MP", name: "Madhya Pradesh" }, { code: "MH", name: "Maharashtra" }, { code: "MN", name: "Manipur" },
  { code: "ML", name: "Meghalaya" }, { code: "MZ", name: "Mizoram" }, { code: "NL", name: "Nagaland" }, { code: "OR", name: "Odisha" },
  { code: "PY", name: "Puducherry" }, { code: "PB", name: "Punjab" }, { code: "RJ", name: "Rajasthan" }, { code: "SK", name: "Sikkim" },
  { code: "TN", name: "Tamil Nadu" }, { code: "TG", name: "Telangana" }, { code: "TR", name: "Tripura" }, { code: "UP", name: "Uttar Pradesh" },
  { code: "UT", name: "Uttarakhand" }, { code: "WB", name: "West Bengal" },
];

const regionNames = typeof Intl.DisplayNames === "function" ? new Intl.DisplayNames(["en"], { type: "region" }) : null;

/** English name of an ISO 3166-1 alpha-2 country code ("US" → "United States"). */
export function countryName(code: string): string {
  try {
    return regionNames?.of(code) ?? code;
  } catch {
    return code;
  }
}
