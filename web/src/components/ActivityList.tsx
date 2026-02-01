/**
 * Activity List component with pagination.
 */

import { useQuery } from '@tanstack/react-query';
import { Activity, getActivities, type ActivityFilters } from '@/lib/api/activities';
import { ActivityCard } from './ActivityCard';
import { Loader2 } from 'lucide-react';

interface ActivityListProps {
  filters: ActivityFilters;
}

export function ActivityList({ filters }: ActivityListProps) {
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['activities', filters],
    queryFn: () => getActivities(filters),
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (isError) {
    return (
      <div className="bg-destructive/10 border border-destructive/20 rounded-lg p-6 text-center">
        <p className="text-destructive font-medium">Error al cargar actividades</p>
        <p className="text-sm text-muted-foreground mt-1">{error.message}</p>
      </div>
    );
  }

  if (!data || data.data.length === 0) {
    return (
      <div className="bg-muted/50 rounded-lg p-12 text-center">
        <p className="text-muted-foreground text-lg">No se encontraron actividades</p>
        <p className="text-muted-foreground/70 text-sm mt-2">
          Intenta ajustar los filtros de búsqueda
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">
          Mostrando <strong>{data.data.length}</strong> de <strong>{data.meta.total}</strong> actividades
        </p>
        <p className="text-sm text-muted-foreground">
          Página {data.meta.page} de {data.meta.totalPages}
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        {data.data.map((activity) => (
          <ActivityCard key={activity.id} activity={activity} />
        ))}
      </div>

      {data.meta.totalPages > 1 && (
        <div className="flex justify-center gap-2 pt-4">
          {/* Pagination handled by parent via filters.page */}
        </div>
      )}
    </div>
  );
}
