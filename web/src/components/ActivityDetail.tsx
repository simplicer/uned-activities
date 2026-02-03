/**
 * Activity Detail component.
 */

import { useQuery } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { getActivity, getSimilarActivities } from '@/lib/api/activities';
import { Loader2, ArrowLeft, Calendar, MapPin, ExternalLink, Clock, BookOpen, Users, GraduationCap, CheckCircle, XCircle } from 'lucide-react';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import type { Staff, PricingTable, LocationDetails, ScheduleDetails, SimilarActivity } from '@/lib/api/activities';
import { ActivityCard } from '@/components/ActivityCard';

export function ActivityDetailPage() {
  const { id } = useParams<{ id: string }>();

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['activity', id],
    queryFn: () => getActivity(id!),
    enabled: !!id,
  });

  const { data: similarData } = useQuery({
    queryKey: ['activity-similar', id],
    queryFn: () => getSimilarActivities(id!, 6),
    enabled: !!id,
  });

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <div className="relative">
          <div className="absolute inset-0 bg-primary/20 rounded-full animate-ping" />
          <Loader2 className="relative w-12 h-12 animate-spin text-primary" />
        </div>
        <p className="mt-4 text-muted-foreground font-medium">Cargando actividad...</p>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="max-w-2xl mx-auto">
        <Link
          to="/activities"
          className="inline-flex items-center gap-2 text-muted-foreground hover:text-primary transition-colors mb-6"
        >
          <ArrowLeft className="w-4 h-4" />
          Volver a la lista
        </Link>
        <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-8 text-center">
          <div className="w-12 h-12 bg-destructive/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <BookOpen className="w-6 h-6 text-destructive" />
          </div>
          <p className="text-destructive font-semibold text-lg mb-2">Error al cargar la actividad</p>
          <p className="text-sm text-muted-foreground">{error.message}</p>
        </div>
      </div>
    );
  }

  if (!data) return null;

  const activity = data.data;
  const staff: Staff | null = activity.staff;
  const pricingTable: PricingTable[] | null = activity.pricingTable;
  const locationDetails: LocationDetails | null = activity.locationDetails;
  const scheduleDetails: ScheduleDetails | null = activity.scheduleDetails;
  const similarActivities: SimilarActivity[] = similarData?.data || [];

  return (
    <div className="max-w-4xl mx-auto">
      {/* Back Button */}
      <Link
        to="/activities"
        className="inline-flex items-center gap-2 text-muted-foreground hover:text-primary transition-colors mb-6 group"
      >
        <ArrowLeft className="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
        <span className="font-medium">Volver a la lista</span>
      </Link>

      {/* Hero Section with UNED Green Gradient */}
      <div className="relative bg-gradient-to-br from-primary via-primary/95 to-primary/90 rounded-t-2xl overflow-hidden">
        {/* Decorative pattern */}
        <div className="absolute inset-0 opacity-10">
          <div className="absolute top-0 right-0 w-64 h-64 bg-white/20 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
          <div className="absolute bottom-0 left-0 w-48 h-48 bg-white/10 rounded-full blur-2xl translate-y-1/2 -translate-x-1/2" />
        </div>

        <div className="relative p-6 lg:p-8">
          {/* Title */}
          <h1 className="text-2xl lg:text-3xl font-bold text-white mb-6 leading-tight">
            {activity.title || 'Sin título'}
          </h1>

          {/* Badges */}
          <div className="flex flex-wrap gap-2">
            {activity.modality && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30">
                {activity.modality === 'online' ? '🌐 Online' :
                 activity.modality === 'in-person' ? '🏛️ Presencial' : '🔄 Híbrido'}
              </span>
            )}
            {activity.center && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30 flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5" />
                {activity.center}
              </span>
            )}
            {activity.typology && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30">
                {activity.typology}
              </span>
            )}
            {activity.area && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30">
                {activity.area}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Content */}
      <article className="bg-card border border-t-0 border-border rounded-b-2xl shadow-sm overflow-hidden">
        <div className="p-6 lg:p-8 space-y-8">
          {/* Description */}
          {activity.description && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3 flex items-center gap-2">
                <BookOpen className="w-5 h-5 text-primary" />
                Descripción
              </h2>
              <p className="text-muted-foreground whitespace-pre-wrap leading-relaxed">
                {activity.description}
              </p>
            </section>
          )}

          {/* Dates & Schedule */}
          <section>
            <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
              <Calendar className="w-5 h-5 text-primary" />
              Fechas y horarios
            </h2>
            <div className="space-y-4">
              <div className="grid md:grid-cols-2 gap-4">
                {activity.startDate && (
                  <div className="bg-gradient-to-br from-primary/5 to-primary/10 border border-primary/20 rounded-xl p-5">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                        <Calendar className="w-5 h-5 text-primary" />
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium">Inicio</p>
                        <p className="font-semibold text-foreground">
                          {format(new Date(activity.startDate), 'd MMMM, yyyy', { locale: es })}
                        </p>
                      </div>
                    </div>
                  </div>
                )}
                {activity.endDate && (
                  <div className="bg-gradient-to-br from-accent/5 to-accent/10 border border-accent/20 rounded-xl p-5">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-accent/20 rounded-lg flex items-center justify-center">
                        <Calendar className="w-5 h-5 text-accent" />
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium">Fin</p>
                        <p className="font-semibold text-foreground">
                          {format(new Date(activity.endDate), 'd MMMM, yyyy', { locale: es })}
                        </p>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {/* Schedule Details */}
              {scheduleDetails && (scheduleDetails.timeStart || scheduleDetails.timeEnd) && (
                <div className="bg-muted/30 rounded-xl p-5 border border-border">
                  <div className="flex items-center gap-3">
                    <Clock className="w-5 h-5 text-primary" />
                    <div>
                      <p className="text-sm text-muted-foreground">Horario</p>
                      <p className="font-semibold text-foreground">
                        {scheduleDetails.timeStart && scheduleDetails.timeEnd
                          ? `De ${scheduleDetails.timeStart} a ${scheduleDetails.timeEnd} h.`
                          : scheduleDetails.timeStart || scheduleDetails.timeEnd}
                        {scheduleDetails.timezone && ` (${scheduleDetails.timezone})`}
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Location Details */}
              {locationDetails && locationDetails.venue && (
                <div className="bg-muted/30 rounded-xl p-5 border border-border">
                  <div className="flex items-start gap-3">
                    <MapPin className="w-5 h-5 text-primary mt-0.5" />
                    <div>
                      <p className="text-sm text-muted-foreground">Lugar</p>
                      <p className="font-semibold text-foreground">{locationDetails.venue}</p>
                      {locationDetails.center && <p className="text-sm text-muted-foreground mt-1">{locationDetails.center}</p>}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </section>

          {/* Pricing Table */}
          {pricingTable && pricingTable.length > 0 ? (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <GraduationCap className="w-5 h-5 text-primary" />
                Precios
              </h2>
              <div className="bg-gradient-to-br from-primary/5 via-primary/[0.03] to-transparent border-2 border-primary/20 rounded-2xl overflow-hidden">
                <table className="w-full">
                  <thead className="bg-primary/10">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">Modalidad</th>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">Tipo</th>
                      <th className="px-4 py-3 text-right text-sm font-semibold text-foreground">Precio</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pricingTable.map((price, index) => (
                      <tr key={index} className={index !== pricingTable.length - 1 ? 'border-t border-primary/10' : ''}>
                        <td className="px-4 py-3 text-sm text-foreground capitalize">{price.modality}</td>
                        <td className="px-4 py-3 text-sm text-muted-foreground">{price.studentType}</td>
                        <td className="px-4 py-3 text-right font-semibold text-primary">
                          {price.amount === 0 ? 'Gratuita' : price.display}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          ) : (
            /* Price Card (fallback when no pricing table) */
            <section className="bg-gradient-to-br from-primary/5 via-primary/[0.03] to-transparent border-2 border-primary/20 rounded-2xl p-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <p className="text-sm text-muted-foreground mb-1 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 bg-primary rounded-full animate-pulse" />
                    Precio
                  </p>
                  {activity.priceDisplay !== null ? (
                    <p className="text-3xl font-bold text-primary">{activity.priceDisplay}</p>
                  ) : activity.isFree ? (
                    <p className="text-2xl font-bold text-green-600">Gratuita</p>
                  ) : (
                    <p className="text-lg text-muted-foreground italic">Consultar precio</p>
                  )}
                </div>
                {activity.enrollmentOpen !== null && (
                  <div
                    className={`inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-all ${
                      activity.enrollmentOpen
                        ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800'
                        : 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
                    }`}
                  >
                    {activity.enrollmentOpen ? (
                      <>
                        <CheckCircle className="w-4 h-4" />
                        Matrícula abierta
                      </>
                    ) : (
                      <>
                        <XCircle className="w-4 h-4" />
                        Matrícula cerrada
                      </>
                    )}
                  </div>
                )}
              </div>
            </section>
          )}

          {/* Staff */}
          {staff && (staff.director || (staff.speakers && staff.speakers.length > 0)) && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <Users className="w-5 h-5 text-primary" />
                Equipo
              </h2>
              <div className="space-y-4">
                {staff.director && (
                  <div className="bg-muted/30 rounded-xl p-4 border border-border">
                    <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium mb-1">Coordinador/a</p>
                    <p className="font-semibold text-foreground">{staff.director.name}</p>
                    {staff.director.role && <p className="text-sm text-muted-foreground mt-1">{staff.director.role}</p>}
                  </div>
                )}
                {staff.speakers && staff.speakers.length > 0 && (
                  <div className="bg-muted/30 rounded-xl p-4 border border-border">
                    <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium mb-3">Ponente/s</p>
                    <div className="space-y-3">
                      {staff.speakers.map((speaker, index) => (
                        <div key={index}>
                          <p className="font-semibold text-foreground">{speaker.name}</p>
                          {speaker.role && <p className="text-sm text-muted-foreground">{speaker.role}</p>}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </section>
          )}

          {/* Target Audience */}
          {activity.targetAudience && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">Dirigido a</h2>
              <p className="text-muted-foreground leading-relaxed">{activity.targetAudience}</p>
            </section>
          )}

          {/* Similar Activities */}
          {similarActivities.length > 0 && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <Users className="w-5 h-5 text-primary" />
                Actividades similares
              </h2>
              <div className="grid gap-4 grid-cols-1">
                {similarActivities.map((similar) => (
                  <ActivityCard key={similar.id} activity={similar} />
                ))}
              </div>
            </section>
          )}

          {/* Footer Info */}
          <footer className="pt-6 border-t border-border">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm">
              <div className="space-y-1">
                <p className="text-muted-foreground">
                  Código UNED: <span className="font-mono font-medium text-foreground">{activity.unedId}</span>
                </p>
                {activity.credits && (
                  <p className="text-muted-foreground">
                    Créditos: <span className="font-medium text-foreground">{activity.credits}</span>
                  </p>
                )}
                <p className="text-muted-foreground">
                  Actualizado: <span className="text-foreground">{format(new Date(activity.updatedAt), 'd MMM yyyy, HH:mm', { locale: es })}</span>
                </p>
              </div>
              {activity.url && (
                <a
                  href={activity.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 transition-colors"
                >
                  Ver en UNED
                  <ExternalLink className="w-4 h-4" />
                </a>
              )}
            </div>
          </footer>
        </div>
      </article>
    </div>
  );
}
