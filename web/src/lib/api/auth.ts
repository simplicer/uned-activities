/**
 * Authentication API methods.
 */

import { buildApiUrl } from './base';

interface ApiError {
  error: string;
  message: string;
}

async function authFetch<T>(endpoint: string, options?: RequestInit): Promise<T> {
  const url = buildApiUrl(endpoint);
  const authToken = getStoredAuthToken();

  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
      ...(options?.headers || {}),
    },
  });

  if (!response.ok) {
    const error: ApiError = await response.json();
    throw new Error(error.message || 'API request failed');
  }

  return response.json();
}

export interface MagicLinkRequest {
  email: string;
}

export interface VerifyTokenRequest {
  token: string;
}

export interface AuthResponse {
  user: {
    id: string;
    email: string;
  };
  token: string;
  message: string;
}

export interface AuthUser {
  id: string;
  email: string;
  token: string;
}

/**
 * Request a magic link for passwordless authentication.
 */
export async function requestMagicLink(email: string): Promise<{ message: string }> {
  return authFetch<{ message: string }>('/auth/request', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
}

/**
 * Verify a magic link token and authenticate.
 */
export async function verifyMagicLink(token: string): Promise<AuthResponse> {
  const response = await authFetch<{ data: AuthResponse }>('/auth/verify', {
    method: 'POST',
    body: JSON.stringify({ token }),
  });
  return response.data;
}

/**
 * Get current authenticated user.
 */
export async function getCurrentUser(): Promise<{ data: AuthUser }> {
  return authFetch<{ data: AuthUser }>('/auth/me');
}

/**
 * Store auth token in localStorage.
 */
export function storeAuthToken(token: string, userId: string, email: string): void {
  if (typeof localStorage === 'undefined') {
    return;
  }
  localStorage.setItem('auth_token', token);
  localStorage.setItem('user_id', userId);
  localStorage.setItem('user_email', email);
}

/**
 * Get stored auth token.
 */
export function getStoredAuthToken(): string | null {
  if (typeof localStorage === 'undefined') {
    return null;
  }
  return localStorage.getItem('auth_token');
}

/**
 * Get stored user info.
 */
export function getStoredUser(): { id: string | null; email: string | null } {
  if (typeof localStorage === 'undefined') {
    return { id: null, email: null };
  }
  return {
    id: localStorage.getItem('user_id'),
    email: localStorage.getItem('user_email'),
  };
}

/**
 * Clear auth token from localStorage.
 */
export function clearAuthToken(): void {
  if (typeof localStorage === 'undefined') {
    return;
  }
  localStorage.removeItem('auth_token');
  localStorage.removeItem('user_id');
  localStorage.removeItem('user_email');
}

/**
 * Check if user is authenticated.
 */
export function isAuthenticated(): boolean {
  return getStoredAuthToken() !== null;
}
