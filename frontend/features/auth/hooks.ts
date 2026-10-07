"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { authApi } from "./api";
import type { LoginResponse, User } from "@/types/api";

export const currentUserKey = ["me"] as const;

export function useCurrentUser() {
  return useQuery({ queryKey: currentUserKey, queryFn: authApi.me, staleTime: 60_000 });
}

function useStoreUser() {
  const queryClient = useQueryClient();
  return (response: LoginResponse | User) => {
    const user = "uuid" in response ? response : response.two_factor_required ? null : response.user;
    if (user) queryClient.setQueryData(currentUserKey, user);
  };
}

export function useLogin() {
  const storeUser = useStoreUser();
  return useMutation({ mutationFn: authApi.login, onSuccess: storeUser });
}

export function useTwoFactorChallenge() {
  const storeUser = useStoreUser();
  return useMutation({ mutationFn: authApi.twoFactorChallenge, onSuccess: storeUser });
}

export function useRegister() {
  const storeUser = useStoreUser();
  return useMutation({ mutationFn: authApi.register, onSuccess: storeUser });
}

export function useLogout() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: authApi.logout,
    onSettled: () => {
      queryClient.clear();
      queryClient.setQueryData(currentUserKey, null);
    },
  });
}
