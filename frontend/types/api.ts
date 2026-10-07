/**
 * Shapes shared with the Laravel API (docs/API.md). Keep in sync with
 * backend API Resources; never include internal numeric ids.
 */

export type ApiSuccess<T> = {
  success: true;
  data: T;
  message: string | null;
  meta: Record<string, unknown>;
};

export type ApiFailure = {
  success: false;
  message: string;
  errors: Record<string, unknown> & { code?: string };
  meta?: { request_id?: string | null };
};

export type ApiEnvelope<T> = ApiSuccess<T> | ApiFailure;

export type Money = {
  amount: number;
  currency: string;
  formatted?: string;
};

export type User = {
  uuid: string;
  name: string;
  email: string;
  email_verified: boolean;
  phone: string | null;
  locale: string;
  two_factor_enabled: boolean;
  is_staff: boolean;
  marketing_opt_in: boolean;
  created_at: string | null;
};

export type LoginResponse =
  | { two_factor_required: true }
  | { two_factor_required: false; user: User };

export type AdminMe = {
  user: User;
  roles: string[];
  permissions: string[];
};

export type TwoFactorSetup = {
  secret: string;
  otpauth_url: string;
  qr_svg: string;
};
