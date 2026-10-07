import { api } from "@/services/api-client";
import type { TwoFactorSetup } from "@/types/api";

export const accountApi = {
  startTwoFactor: (password: string) => api.post<TwoFactorSetup>("/account/two-factor", { password }),
  confirmTwoFactor: (code: string) => api.post<{ recovery_codes: string[] }>("/account/two-factor/confirm", { code }),
};
