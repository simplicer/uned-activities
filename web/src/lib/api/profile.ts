/**
 * Profile API methods.
 */

import { apiFetch } from '../api';

export interface UserProfile {
  id: string;
  email: string;
  fullName: string | null;
  preferences: Record<string, unknown>;
  createdAt: string;
}

export interface SavedSearch {
  id: string;
  name: string;
  filters: Record<string, unknown>;
  notifyOnNew: boolean;
  createdAt: string;
}

export interface FavoriteActivity {
  activity_id: string;
  created_at: string;
  enrolled: boolean | null;
  user_rating: number | null;
  notify_on_change: boolean | null;
  id: string;
  uned_id: string;
  url: string;
  title: string | null;
  description: string | null;
  start_date: string | null;
  end_date: string | null;
  modality: string | null;
  center: string | null;
  typology: string | null;
  area: string | null;
  price_amount: number | null;
  price_currency: string | null;
  price_display: string | null;
  credits: number | null;
  image_url: string | null;
  enrollment_open: boolean | null;
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
  preferences?: Record<string, unknown>;
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
  filters: Record<string, unknown>;
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

/**
 * Get favorite activity IDs.
 */
export async function getFavoriteIds(): Promise<string[]> {
  const response = await apiFetch<{ data: string[] | { items?: string[] } }>('/profile/favorites/ids');
  if (Array.isArray(response.data)) {
    return response.data;
  }
  if (response.data && Array.isArray((response.data as { items?: string[] }).items)) {
    return (response.data as { items?: string[] }).items ?? [];
  }
  return [];
}

/**
 * Get favorite activities.
 */
export async function getFavorites(): Promise<FavoriteActivity[]> {
  const response = await apiFetch<{ data: FavoriteActivity[] | { items?: FavoriteActivity[] } }>('/profile/favorites');
  if (Array.isArray(response.data)) {
    return response.data;
  }
  if (response.data && Array.isArray((response.data as { items?: FavoriteActivity[] }).items)) {
    return (response.data as { items?: FavoriteActivity[] }).items ?? [];
  }
  return [];
}

/**
 * Add favorite activity.
 */
export async function addFavorite(activityId: string): Promise<{ added: boolean }> {
  const response = await apiFetch<{ data: { added: boolean } }>('/profile/favorites', {
    method: 'POST',
    body: JSON.stringify({ activityId }),
  });
  return response.data;
}

/**
 * Remove favorite activity.
 */
export async function removeFavorite(activityId: string): Promise<{ deleted: boolean }> {
  const response = await apiFetch<{ data: { deleted: boolean } }>(`/profile/favorites/${activityId}`, {
    method: 'DELETE',
  });
  return response.data;
}

/**
 * Update favorite metadata.
 */
export async function updateFavorite(activityId: string, data: {
  enrolled?: boolean;
  rating?: number | null;
  notifyOnChange?: boolean;
}): Promise<{ updated: boolean }> {
  const response = await apiFetch<{ data: { updated: boolean } }>(`/profile/favorites/${activityId}`, {
    method: 'PUT',
    body: JSON.stringify(data),
  });
  return response.data;
}

/**
 * Set account password.
 */
export async function setPassword(password: string): Promise<{ updated: boolean }> {
  const response = await apiFetch<{ data: { updated: boolean } }>('/profile/password', {
    method: 'PUT',
    body: JSON.stringify({ password }),
  });
  return response.data;
}

/**
 * Get notifications.
 */
export async function getNotifications(): Promise<Array<{
  id: string;
  type: string;
  title: string;
  message: string;
  data: Record<string, unknown>;
  isRead: boolean;
  createdAt: string;
}>> {
  const response = await apiFetch<{ data: Array<{
    id: string;
    type: string;
    title: string;
    message: string;
    data: Record<string, unknown>;
    isRead: boolean;
    createdAt: string;
  }> | { items?: Array<{
    id: string;
    type: string;
    title: string;
    message: string;
    data: Record<string, unknown>;
    isRead: boolean;
    createdAt: string;
  }> } }>('/profile/notifications');
  if (Array.isArray(response.data)) {
    return response.data;
  }
  if (response.data && Array.isArray((response.data as { items?: Array<{
    id: string;
    type: string;
    title: string;
    message: string;
    data: Record<string, unknown>;
    isRead: boolean;
    createdAt: string;
  }> }).items)) {
    return (response.data as { items?: Array<{
      id: string;
      type: string;
      title: string;
      message: string;
      data: Record<string, unknown>;
      isRead: boolean;
      createdAt: string;
    }> }).items ?? [];
  }
  return [];
}

/**
 * Mark notification as read.
 */
export async function markNotificationRead(id: string): Promise<{ updated: boolean }> {
  const response = await apiFetch<{ data: { updated: boolean } }>(`/profile/notifications/${id}/read`, {
    method: 'POST',
  });
  return response.data;
}

/**
 * Delete account.
 */
export async function deleteAccount(): Promise<{ deleted: boolean }> {
  const response = await apiFetch<{ data: { deleted: boolean } }>('/profile', {
    method: 'DELETE',
  });
  return response.data;
}
