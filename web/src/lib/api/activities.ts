/**
 * Activity API methods.
 */

import { apiFetch, type ApiResponse } from '../api';

export interface Activity {
  id: string;
  unedId: string;
  url: string;
  title: string | null;
  description: string | null;
  startDate: string | null;
  endDate: string | null;
  modality: string | null;
  center: string | null;
  typology: string | null;
  area: string | null;
  priceAmount: number | null;
  priceCurrency: string | null;
  priceDisplay: string | null;
  enrollmentOpen: boolean | null;
  enrollmentStartDate: string | null;
  enrollmentEndDate: string | null;
  status: string;
  createdAt: string;
  updatedAt: string;
  hash: string;
}

export interface PriceSnapshot {
  priceAmount: number | null;
  priceCurrency: string | null;
  capturedAt: string;
}

export interface ActivityDetail extends Activity {
  priceHistory: PriceSnapshot[];
}

export interface ActivityFilters {
  center?: string;
  typology?: string;
  area?: string;
  modality?: string;
  minPrice?: number;
  maxPrice?: number;
  startDateFrom?: string;
  startDateTo?: string;
  search?: string;
  page?: number;
  perPage?: number;
}

export interface ActivityListResponse {
  data: Activity[];
  meta: {
    total: number;
    page: number;
    perPage: number;
    totalPages: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
  };
}

/**
 * Get list of activities with filters.
 */
export async function getActivities(
  filters: ActivityFilters = {}
): Promise<ActivityListResponse> {
  const params = new URLSearchParams();

  if (filters.center) params.append('center', filters.center);
  if (filters.typology) params.append('typology', filters.typology);
  if (filters.area) params.append('area', filters.area);
  if (filters.modality) params.append('modality', filters.modality);
  if (filters.minPrice !== undefined) params.append('minPrice', filters.minPrice.toString());
  if (filters.maxPrice !== undefined) params.append('maxPrice', filters.maxPrice.toString());
  if (filters.startDateFrom) params.append('startDateFrom', filters.startDateFrom);
  if (filters.startDateTo) params.append('startDateTo', filters.startDateTo);
  if (filters.search) params.append('search', filters.search);
  params.append('page', (filters.page || 1).toString());
  params.append('perPage', (filters.perPage || 20).toString());

  const query = params.toString();
  return apiFetch<Activity[]>(`/activities${query ? `?${query}` : ''}`) as Promise<ActivityListResponse>;
}

/**
 * Get activity detail by ID.
 */
export async function getActivity(id: string): Promise<{ data: ActivityDetail }> {
  return apiFetch<ActivityDetail>(`/activities/${id}`);
}
