/**
 * Shared API utility functions.
 */

import { getStoredAuthToken } from './auth';
import { buildApiUrl } from './base';

export async function apiFetch<T>(endpoint: string, options?: RequestInit): Promise<T> {
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
    try {
      const errorData = await response.json();
      throw new Error(errorData.message || 'API request failed');
    } catch (parseError) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
  }

  try {
    return response.json();
  } catch (parseError) {
    throw new Error('Invalid JSON response from API');
  }
}

export interface ApiResponse<T> {
  data: T;
  meta?: {
    total?: number;
    page?: number;
    perPage?: number;
    totalPages?: number;
    hasNextPage?: boolean;
    hasPrevPage?: boolean;
  };
}

export async function apiFetchWithMeta<T>(endpoint: string, options?: RequestInit): Promise<ApiResponse<T>> {
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
    try {
      const errorData = await response.json();
      throw new Error(errorData.message || 'API request failed');
    } catch (parseError) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
  }

  try {
    const data = await response.json();
    return {
      data: data.data,
      meta: data.meta,
    };
  } catch (parseError) {
    throw new Error('Invalid JSON response from API');
  }
}
