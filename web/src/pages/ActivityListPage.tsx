/**
 * Activity List Page component.
 */

import { useEffect, useState } from 'react';
import { FilterSidebar } from '@/components/FilterSidebar';
import { SORT_OPTIONS } from '@/lib/api/activities';
import { ActivityList } from '@/components/ActivityList';
import { Pagination } from '@/components/Pagination';
import type { ActivityFilters } from '@/lib/api/activities';
import { useQuery } from '@tanstack/react-query';
import { getActivities } from '@/lib/api/activities';
import { useDebounce } from '@/hooks/useDebounce';
import { useTranslation } from 'react-i18next';

interface ActivityListPageProps {
  isFilterOpen: boolean;
  onToggleFilter: () => void;
}

export function ActivityListPage({ isFilterOpen, onToggleFilter }: ActivityListPageProps) {
  const { t } = useTranslation();
  const [filters, setFilters] = useState<ActivityFilters>(() => {
    try {
      const raw = localStorage.getItem('activities_filters');
      if (raw) {
        const parsed = JSON.parse(raw) as ActivityFilters;
        return { ...parsed, page: parsed.page ?? 1, perPage: parsed.perPage ?? 12 };
      }
    } catch {
      // Ignore invalid local storage
    }
    return { page: 1, perPage: 12 };
  });
  const debouncedFilters = useDebounce(filters, 500);

  // Fetch to get pagination metadata
  const { data } = useQuery({
    queryKey: ['activities', debouncedFilters],
    queryFn: () => getActivities(debouncedFilters),
  });

  const handlePageChange = (page: number) => {
    setFilters((prev) => ({ ...prev, page }));
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleFiltersChange = (newFilters: ActivityFilters) => {
    setFilters({ ...newFilters, page: 1 }); // Reset to page 1 when filters change
  };

  useEffect(() => {
    try {
      localStorage.setItem('activities_filters', JSON.stringify(filters));
    } catch {
      // Ignore storage errors
    }
  }, [filters]);

  useEffect(() => {
    if (typeof document === 'undefined') return;
    document.title = t('meta.siteName');
    const metaDescription = document.querySelector('meta[name="description"]') as HTMLMetaElement | null;
    if (metaDescription) {
      metaDescription.content = t('meta.defaultDescription');
    }
  }, [t]);

  return (
    <div className="flex gap-6">
      {/* Filters Sidebar */}
      <FilterSidebar
        filters={filters}
        onFiltersChange={handleFiltersChange}
        isOpen={isFilterOpen}
        onToggle={onToggleFilter}
      />

      {/* Main Content */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-end mb-4">
          <label className="text-sm text-muted-foreground mr-2 whitespace-nowrap">
            {t('filters.sort')}
          </label>
          <select
            value={filters.sort || 'cercania'}
            onChange={(e) => setFilters({ ...filters, sort: e.target.value, page: 1 })}
            className="text-sm rounded-lg border border-border bg-background px-3 py-2"
          >
            {SORT_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {t(option.labelKey)}
              </option>
            ))}
          </select>
        </div>
        <ActivityList filters={debouncedFilters} />

        {/* Pagination */}
        {data?.meta && (
          <div className="mt-8">
            <Pagination
              currentPage={data.meta.page || 1}
              totalPages={data.meta.totalPages || 1}
              hasNextPage={data.meta.hasNextPage || false}
              hasPrevPage={data.meta.hasPrevPage || false}
              onPageChange={handlePageChange}
            />
          </div>
        )}
      </div>
    </div>
  );
}
