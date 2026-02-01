/**
 * Profile API methods.
 */

import { apiFetch, type ApiResponse } from '../api';

export interface UserProfile {
  id: string;
  email: string;
  fullName: string | null;
  preferences: Record<string, any>;
  createdAt: string;
}

export interface SavedSearch {
  id: string;
  name: string;
  filters: Record<string, any>;
  notifyOnNew: boolean;
  createdAt: string;
}

/**
 * Get current user profile.
 */
export async function getProfile(): Promise<ApiResponse<UserProfile>> {
  return apiFetch<UserProfile>('/profile');
}

/**
 * Update user profile.
 */
export async function updateProfile(data: {
  fullName?: string;
  preferences?: Record<string, any>;
}): Promise<ApiResponse<{ updated: boolean }>> {
  return apiFetch<{ updated: boolean }>('/profile', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

/**
 * Get saved searches.
 */
export async function getSavedSearches(): Promise<ApiResponse<SavedSearch[]>> {
  return apiFetch<SavedSearch[]>('/profile/saved-searches');
}

/**
 * Create saved search.
 */
export async function createSavedSearch(data: {
  name: string;
  filters: Record<string, any>;
  notifyOnNew?: boolean;
}): Promise<ApiResponse<SavedSearch>> {
  return apiFetch<SavedSearch>('/profile/saved-searches', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

/**
 * Delete saved search.
 */
export async function deleteSavedSearch(id: string): Promise<ApiResponse<{ deleted: boolean }>> {
  return apiFetch<{ deleted: boolean }>(`/profile/saved-searches/${id}`, {
    method: 'DELETE',
  });
}
