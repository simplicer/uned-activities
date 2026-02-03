/**
 * Activity List component with pagination.
 */

import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { getActivities, type ActivityFilters } from '@/lib/api/activities';
import { ActivityCard } from './ActivityCard';
import { Loader2, Search, FileQuestion } from 'lucide-react';

interface ActivityListProps {
  filters: ActivityFilters;
}

export function ActivityList({ filters }: ActivityListProps) {
  const { t } = useTranslation();
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['activities', filters],
    queryFn: () => getActivities(filters),
  });

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-16">
        <div className="relative">
          <div className="absolute inset-0 bg-primary/20 rounded-full animate-ping" />
          <Loader2 className="relative w-12 h-12 animate-spin text-primary" />
        </div>
        <p className="mt-4 text-muted-foreground font-medium">{t('loading')}</p>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-8 text-center">
        <div className="w-12 h-12 bg-destructive/20 rounded-full flex items-center justify-center mx-auto mb-4">
          <FileQuestion className="w-6 h-6 text-destructive" />
        </div>
        <p className="text-destructive font-semibold text-lg mb-2">{t('error')}</p>
        <p className="text-sm text-muted-foreground">{error.message}</p>
      </div>
    );
  }

  if (!data || data.data.length === 0) {
    return (
      <div className="bg-muted/30 rounded-xl p-12 text-center border border-dashed border-muted-foreground/30">
        <Search className="w-16 h-16 text-muted-foreground/30 mx-auto mb-4" />
        <p className="text-muted-foreground text-xl font-semibold mb-2">{t('activities.noResults')}</p>
        <p className="text-muted-foreground/70 text-sm max-w-md mx-auto">
          {t('activities.tryDifferentFilters')}
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Results Summary */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white rounded-lg p-4 border border-border">
        <div className="flex items-center gap-2">
          <div className="w-2 h-2 bg-primary rounded-full animate-pulse" />
          <p className="text-sm text-muted-foreground">
            {t('page.showing')} <span className="font-semibold text-foreground">{data.data.length}</span> {t('page.of')}{' '}
            <span className="font-semibold text-foreground">{data.meta?.total || 0}</span> {t('activities.title')}
          </p>
        </div>
        <div className="flex items-center gap-2 text-sm bg-muted/50 px-3 py-1.5 rounded-full">
          <span className="text-muted-foreground">{t('page.page')}</span>
          <span className="font-semibold text-foreground">{data.meta?.page || 1}</span>
          <span className="text-muted-foreground">{t('page.of')}</span>
          <span className="font-semibold text-foreground">{data.meta?.totalPages || 1}</span>
        </div>
      </div>

      {/* Activity Grid - Single column layout */}
      <div className="grid gap-6 grid-cols-1">
        {data.data.map((activity: any) => (
          <ActivityCard key={activity.id} activity={activity} />
        ))}
      </div>

      {/* Pagination placeholder */}
      {(data.meta?.totalPages && data.meta.totalPages > 1) && (
        <div className="flex justify-center gap-2 pt-4">
          {/* Pagination handled by parent via filters.page */}
        </div>
      )}
    </div>
  );
}
