/**
 * Activity List component with pagination.
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { getActivities, type Activity, type ActivityFilters } from '@/lib/api/activities';
import { ActivityCard } from './ActivityCard';
import { Loader2, Search, FileQuestion } from 'lucide-react';
import { useAuth } from '@/contexts/useAuth';
import { addFavorite, getFavoriteIds, removeFavorite } from '@/lib/api/profile';

interface ActivityListProps {
  filters: ActivityFilters;
}

export function ActivityList({ filters }: ActivityListProps) {
  const { t } = useTranslation();
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['activities', filters],
    queryFn: () => getActivities(filters),
  });

  const { data: favoriteIds } = useQuery({
    queryKey: ['favorite-ids'],
    queryFn: () => getFavoriteIds(),
    enabled: !!user,
  });

  const addFavoriteMutation = useMutation({
    mutationFn: addFavorite,
    onMutate: async (activityId: string) => {
      await queryClient.cancelQueries({ queryKey: ['favorite-ids'] });
      const previous = queryClient.getQueryData<string[]>(['favorite-ids']) || [];
      if (!previous.includes(activityId)) {
        queryClient.setQueryData(['favorite-ids'], [...previous, activityId]);
      }
      return { previous };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorite-ids'] });
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
    },
    onError: (_error, activityId, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['favorite-ids'], context.previous);
      } else {
        queryClient.setQueryData(['favorite-ids'], (current: string[] | undefined) =>
          (current || []).filter((id) => id !== activityId)
        );
      }
    },
  });

  const removeFavoriteMutation = useMutation({
    mutationFn: removeFavorite,
    onMutate: async (activityId: string) => {
      await queryClient.cancelQueries({ queryKey: ['favorite-ids'] });
      const previous = queryClient.getQueryData<string[]>(['favorite-ids']) || [];
      queryClient.setQueryData(
        ['favorite-ids'],
        previous.filter((id) => id !== activityId)
      );
      return { previous };
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorite-ids'] });
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
    },
    onError: (_error, _activityId, context) => {
      if (context?.previous) {
        queryClient.setQueryData(['favorite-ids'], context.previous);
      }
    },
  });

  const toggleFavorite = (activityId: string) => {
    if (!user) return;
    const isFavorite = favoriteIds?.includes(activityId);
    if (isFavorite) {
      removeFavoriteMutation.mutate(activityId);
    } else {
      addFavoriteMutation.mutate(activityId);
    }
  };

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

  const total = data.meta?.total || 0;
  const perPage = data.meta?.perPage || data.data.length || 1;
  const page = data.meta?.page || 1;
  const start = total === 0 ? 0 : (page - 1) * perPage + 1;
  const end = total === 0 ? 0 : Math.min(page * perPage, total);

  return (
    <div className="space-y-6">
      {/* Results Summary */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card rounded-lg p-4 border border-border">
        <div className="flex items-center gap-2">
          <div className="w-2 h-2 bg-primary rounded-full animate-pulse" />
          <p className="text-sm text-muted-foreground">
            {t('page.showing')} <span className="font-semibold text-foreground">{start}-{end}</span> {t('page.of')}{' '}
            <span className="font-semibold text-foreground">{total}</span> {t('activities.title')}
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
        {data.data.map((activity: Activity) => (
          <ActivityCard
            key={activity.id}
            activity={activity}
            isFavorite={favoriteIds?.includes(activity.id)}
            onToggleFavorite={user ? toggleFavorite : undefined}
          />
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
