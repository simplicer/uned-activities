/**
 * Activity List Page component.
 */

import { useState } from 'react';
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
  const [filters, setFilters] = useState<ActivityFilters>({ page: 1, perPage: 12 });
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
              currentPage={data.meta.page}
              totalPages={data.meta.totalPages}
              hasNextPage={data.meta.hasNextPage}
              hasPrevPage={data.meta.hasPrevPage}
              onPageChange={handlePageChange}
            />
          </div>
        )}
      </div>
    </div>
  );
}
