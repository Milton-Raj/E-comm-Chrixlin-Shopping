import { api, apiRequestWithMeta, type Pagination } from "@/services/api-client";

export type PublicReview = { uuid: string; author: string; rating: number; title: string | null; body: string; verified: boolean; photos: { url: string }[]; created_at: string | null };
export type ReviewSummary = { average: number; count: number; histogram: Record<"1" | "2" | "3" | "4" | "5", number> };

export const reviewsApi = {
  list: async (slug: string, page: number) => {
    const { data, meta } = await apiRequestWithMeta<{ summary: ReviewSummary; reviews: PublicReview[] }>(`/products/${encodeURIComponent(slug)}/reviews?page=${page}`);
    return { ...data, pagination: meta.pagination as Pagination };
  },
  submit: (slug: string, form: FormData) => api.post<{ uuid: string; status: string }>(`/products/${encodeURIComponent(slug)}/reviews`, form),
};
