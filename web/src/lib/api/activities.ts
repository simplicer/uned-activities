/**
 * Activity API methods.
 */

import { apiFetch, apiFetchWithMeta, type ApiResponse } from '../api';

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
  pricingTable?: PricingTable[] | null;
  credits?: number | null;
  imageUrl?: string | null;
  enrollmentOpen: boolean | null;
  enrollmentStartDate: string | null;
  enrollmentEndDate: string | null;
  enrollmentLink?: string | null;
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

export interface Staff {
  director?: { name: string; role?: string } | null;
  speakers?: Array<{ name: string; role?: string; bio?: string }> | null;
}

export interface PricingTable {
  modality: string | null;
  modalityLabel?: string | null;
  studentType: string;
  amount: number;
  currency: string;
  display: string;
}

export interface CenterOption {
  name: string;
  count: number;
}

export interface LocationDetails {
  venue?: string | null;
  center?: string | null;
  address?: string | null;
  city?: string | null;
}

export interface ScheduleDetails {
  timeStart?: string | null;
  timeEnd?: string | null;
  timezone?: string | null;
}

export interface ActivityDetail extends Omit<Activity, 'priceHistory'> {
  isFree: boolean | null;
  credits: number | null;
  hasLive: boolean | null;
  hasRecorded: boolean | null;
  staff: Staff | null;
  pricingTable: PricingTable[] | null;
  locationDetails: LocationDetails | null;
  scheduleDetails: ScheduleDetails | null;
  targetAudience: string | null;
  requirements: Record<string, unknown> | null;
  sessions: Record<string, unknown>[] | null;
}

export interface ActivityFilters {
  center?: string;
  typology?: string;
  area?: string;
  modality?: string;
  freeOnly?: boolean;
  deliveryMode?: 'live' | 'recorded';
  withCredits?: boolean;
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

export interface SimilarActivity extends Activity {
  similarity: number;
}

/**
 * Get list of activities with filters.
 */
export async function getActivities(
  filters: ActivityFilters = {}
): Promise<ApiResponse<Activity[]>> {
  const params = new URLSearchParams();

  if (filters.center) params.append('center', filters.center);
  if (filters.typology) params.append('typology', filters.typology);
  if (filters.area) params.append('area', filters.area);
  if (filters.modality) params.append('modality', filters.modality);
  if (filters.freeOnly) params.append('freeOnly', 'true');
  if (filters.deliveryMode) params.append('deliveryMode', filters.deliveryMode);
  if (filters.withCredits) params.append('withCredits', 'true');
  if (filters.minPrice !== undefined) params.append('minPrice', filters.minPrice.toString());
  if (filters.maxPrice !== undefined) params.append('maxPrice', filters.maxPrice.toString());
  if (filters.startDateFrom) params.append('startDateFrom', filters.startDateFrom);
  if (filters.startDateTo) params.append('startDateTo', filters.startDateTo);
  if (filters.search) params.append('search', filters.search);
  params.append('page', (filters.page || 1).toString());
  params.append('perPage', (filters.perPage || 20).toString());

  const query = params.toString();
  return apiFetchWithMeta<Activity[]>(`/activities${query ? `?${query}` : ''}`);
}

/**
 * Get activity detail by ID.
 */
export async function getActivity(id: string): Promise<ApiResponse<ActivityDetail>> {
  return apiFetchWithMeta<ActivityDetail>(`/activities/${id}`);
}

/**
 * Get similar activities by ID.
 */
export async function getSimilarActivities(id: string, limit = 5): Promise<ApiResponse<SimilarActivity[]>> {
  return apiFetchWithMeta<SimilarActivity[]>(`/activities/${id}/similar?limit=${limit}`);
}

/**
 * Get centers with activity counts.
 */
export async function getCenters(): Promise<ApiResponse<CenterOption[]>> {
  return apiFetch<ApiResponse<CenterOption[]>>('/centers');
}
