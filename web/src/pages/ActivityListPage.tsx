/**
 * Activity List Page component.
 */

import { useEffect, useState } from 'react';
import { FilterSidebar } from '@/components/FilterSidebar';
import { ActivityList } from '@/components/ActivityList';
import { Pagination } from '@/components/Pagination';
import type { ActivityFilters } from '@/lib/api/activities';
import { useQuery } from '@tanstack/react-query';
import { getActivities } from '@/lib/api/activities';
import { useDebounce } from '@/hooks/useDebounce';

interface ActivityListPageProps {
  isFilterOpen: boolean;
  onToggleFilter: () => void;
}

export function ActivityListPage({ isFilterOpen, onToggleFilter }: ActivityListPageProps) {
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
