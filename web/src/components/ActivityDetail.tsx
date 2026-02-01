/**
 * Activity Detail component.
 */

import { useQuery } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { getActivity, type ActivityDetail } from '@/lib/api/activities';
import { Loader2, ArrowLeft, TrendingUp, TrendingDown, Minus } from 'lucide-react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export function ActivityDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['activity', id],
    queryFn: () => getActivity(id!),
    enabled: !!id,
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
        <p className="text-destructive font-medium">Error al cargar la actividad</p>
        <p className="text-sm text-muted-foreground mt-1">{error.message}</p>
      </div>
    );
  }

  if (!data) return null;

  const activity = data.data;

  return (
    <div className="max-w-4xl mx-auto">
      <Link
        to="/activities"
        className="inline-flex items-center gap-2 text-muted-foreground hover:text-foreground mb-6"
      >
        <ArrowLeft className="w-4 h-4" />
        Volver a la lista
      </Link>

      <article className="bg-card border rounded-lg overflow-hidden">
        {/* Header */}
        <div className="p-6 border-b bg-muted/30">
          <h1 className="text-2xl font-bold mb-4">{activity.title || 'Sin título'}</h1>

          <div className="flex flex-wrap gap-2">
            {activity.modality && (
              <span className="px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium">
                {activity.modality === 'online' ? 'Online' :
                 activity.modality === 'in-person' ? 'Presencial' : 'Híbrido'}
              </span>
            )}
            {activity.center && (
              <span className="px-3 py-1 bg-muted text-muted-foreground rounded-full text-sm">
                📍 {activity.center}
              </span>
            )}
            {activity.typology && (
              <span className="px-3 py-1 bg-secondary/50 text-secondary-foreground rounded-full text-sm">
                {activity.typology}
              </span>
            )}
            {activity.area && (
              <span className="px-3 py-1 bg-accent/50 text-accent-foreground rounded-full text-sm">
                {activity.area}
              </span>
            )}
          </div>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Description */}
          {activity.description && (
            <div>
              <h2 className="font-semibold mb-2">Descripción</h2>
              <p className="text-muted-foreground whitespace-pre-wrap">
                {activity.description}
              </p>
            </div>
          )}

          {/* Dates */}
          <div className="grid md:grid-cols-2 gap-4">
            {activity.startDate && (
              <div className="bg-muted/50 rounded-lg p-4">
                <p className="text-sm text-muted-foreground mb-1">Fecha de inicio</p>
                <p className="font-medium">
                  {format(new Date(activity.startDate), 'd MMMM, yyyy', { locale: es })}
                </p>
              </div>
            )}
            {activity.endDate && (
              <div className="bg-muted/50 rounded-lg p-4">
                <p className="text-sm text-muted-foreground mb-1">Fecha de fin</p>
                <p className="font-medium">
                  {format(new Date(activity.endDate), 'd MMMM, yyyy', { locale: es })}
                </p>
              </div>
            )}
          </div>

          {/* Price */}
          <div className="bg-primary/5 border border-primary/20 rounded-lg p-4">
            <div className="flex items-baseline justify-between">
              <div>
                <p className="text-sm text-muted-foreground mb-1">Precio</p>
                {activity.priceDisplay !== null ? (
                  <p className="text-2xl font-bold text-primary">{activity.priceDisplay}</p>
                ) : (
                  <p className="text-lg text-muted-foreground">Consultar precio</p>
                )}
              </div>
              {activity.enrollmentOpen !== null && (
                <div
                  className={`px-3 py-1 rounded-full text-sm font-medium ${
                    activity.enrollmentOpen
                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400'
                  }`}
                >
                  {activity.enrollmentOpen ? '✓ Matrícula abierta' : '✗ Cerrada'}
                </div>
              )}
            </div>

            {/* Enrollment dates */}
            {(activity.enrollmentStartDate || activity.enrollmentEndDate) && (
              <div className="mt-3 pt-3 border-t border-primary/20 text-sm">
                {activity.enrollmentStartDate && (
                  <p className="text-muted-foreground">
                    Matrícula desde: {format(new Date(activity.enrollmentStartDate), 'd MMM', { locale: es })}
                  </p>
                )}
                {activity.enrollmentEndDate && (
                  <p className="text-muted-foreground">
                    Matrícula hasta: {format(new Date(activity.enrollmentEndDate), 'd MMM', { locale: es })}
                  </p>
                )}
              </div>
            )}
          </div>

          {/* Price History */}
          {activity.priceHistory.length > 0 && (
            <div>
              <h2 className="font-semibold mb-3">Historial de precios</h2>
              <div className="space-y-2">
                {activity.priceHistory.map((snapshot, index) => {
                  const prev = activity.priceHistory[index + 1];
                  let trend = null;
                  if (prev && snapshot.priceAmount !== null && prev.priceAmount !== null) {
                    trend = snapshot.priceAmount > prev.priceAmount ? 'up' :
                            snapshot.priceAmount < prev.priceAmount ? 'down' : 'same';
                  }

                  return (
                    <div
                      key={index}
                      className="flex items-center justify-between p-3 bg-muted/30 rounded-lg"
                    >
                      <div>
                        <p className="text-sm text-muted-foreground">
                          {format(new Date(snapshot.capturedAt), 'd MMM yyyy, HH:mm', { locale: es })}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        {trend === 'up' && <TrendingUp className="w-4 h-4 text-green-500" />}
                        {trend === 'down' && <TrendingDown className="w-4 h-4 text-red-500" />}
                        {trend === 'same' && <Minus className="w-4 h-4 text-gray-500" />}
                        <span className="font-medium">
                          {snapshot.priceAmount !== null
                            ? `${(snapshot.priceAmount / 100).toFixed(2)}€`
                            : 'N/A'}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Footer info */}
          <div className="text-sm text-muted-foreground pt-4 border-t">
            <p>Código UNED: {activity.unedId}</p>
            <p className="mt-1">
              Actualizado: {format(new Date(activity.updatedAt), 'd MMM yyyy, HH:mm', { locale: es })}
            </p>
            {activity.url && (
              <a
                href={activity.url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary hover:underline mt-2 inline-block"
              >
                Ver en UNED →
              </a>
            )}
          </div>
        </div>
      </article>
    </div>
  );
}
