/**
 * Profile API methods.
 */

import { apiFetch } from '../api';

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
export async function getProfile(): Promise<UserProfile> {
  const response = await apiFetch<{ data: UserProfile }>('/profile');
  return response.data;
}

/**
 * Update user profile.
 */
export async function updateProfile(data: {
  fullName?: string;
  preferences?: Record<string, any>;
}): Promise<{ updated: boolean }> {
  const response = await apiFetch<{ data: { updated: boolean } }>('/profile', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
  return response.data;
}

/**
 * Get saved searches.
 */
export async function getSavedSearches(): Promise<SavedSearch[]> {
  const response = await apiFetch<{ data: SavedSearch[] }>('/profile/saved-searches');
  return response.data;
}

/**
 * Create saved search.
 */
export async function createSavedSearch(data: {
  name: string;
  filters: Record<string, any>;
  notifyOnNew?: boolean;
}): Promise<SavedSearch> {
  const response = await apiFetch<{ data: SavedSearch }>('/profile/saved-searches', {
    method: 'POST',
    body: JSON.stringify(data),
  });
  return response.data;
}

/**
 * Delete saved search.
 */
export async function deleteSavedSearch(id: string): Promise<{ deleted: boolean }> {
  const response = await apiFetch<{ data: { deleted: boolean } }>(`/profile/saved-searches/${id}`, {
    method: 'DELETE',
  });
  return response.data;
}
