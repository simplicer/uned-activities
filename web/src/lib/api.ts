/**
 * API client configuration and base fetcher.
 */

const API_BASE = import.meta.env.VITE_API_BASE || 'http://localhost:8080';
const API_TOKEN = import.meta.env.VITE_API_TOKEN || '';

interface ApiResponse<T> {
  data: T;
  meta?: {
    total: number;
    page: number;
    perPage: number;
    totalPages: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
  };
}

interface ApiError {
  error: string;
  message: string;
}

async function apiFetch<T>(
  endpoint: string,
  options?: RequestInit
): Promise<ApiResponse<T>> {
  const url = `${API_BASE}${endpoint}`;

  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    ...(options?.headers || {}),
  };

  if (API_TOKEN) {
    headers['Authorization'] = `Bearer ${API_TOKEN}`;
    headers['X-API-Token'] = API_TOKEN;
  }

  const response = await fetch(url, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const error: ApiError = await response.json();
    throw new Error(error.message || 'API request failed');
  }

  return response.json();
}

export { apiFetch, type ApiResponse, type ApiError };
