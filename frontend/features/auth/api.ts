import { api, ApiError } from "@/services/api-client";
import type { LoginResponse, User } from "@/types/api";

export const authApi = {
  /** The signed-in user, or null for guests. */
  async me(): Promise<User | null> {
    try {
      return await api.get<User>("/me");
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) return null;
      throw error;
    }
  },
  login: (input: { email: string; password: string; remember: boolean }) =>
    api.post<LoginResponse>("/auth/login", input),
  twoFactorChallenge: (input: { code?: string; recovery_code?: string }) =>
    api.post<LoginResponse>("/auth/two-factor/challenge", input),
  register: (input: { name: string; email: string; password: string; password_confirmation: string; marketing_opt_in: boolean }) =>
    api.post<User>("/auth/register", input),
  logout: () => api.post<Record<string, never>>("/auth/logout"),
  forgotPassword: (input: { email: string }) => api.post<Record<string, never>>("/auth/forgot-password", input),
  resetPassword: (input: { token: string; email: string; password: string; password_confirmation: string }) =>
    api.post<Record<string, never>>("/auth/reset-password", input),
  resendVerification: () => api.post<Record<string, never>>("/auth/email/resend"),
};
