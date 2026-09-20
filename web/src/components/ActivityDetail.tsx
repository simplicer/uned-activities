/**
 * Activity Detail component.
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams, Link } from 'react-router-dom';
import { getActivity, getSimilarActivities, type ActivityDetail, type Staff, type PricingTable, type LocationDetails, type ScheduleDetails, type SimilarActivity } from '@/lib/api/activities';
import { Loader2, ArrowLeft, Calendar, MapPin, ExternalLink, Clock, BookOpen, Users, GraduationCap, CheckCircle, XCircle, Star, Bell, BellOff } from 'lucide-react';
import { ActivityCard } from '@/components/ActivityCard';
import { useTranslation } from 'react-i18next';
import { useCallback, useEffect } from 'react';
import { useAuth } from '@/contexts/useAuth';
import { addFavorite, getFavoriteIds, getFavorites, removeFavorite, updateFavorite } from '@/lib/api/profile';
import { safeHref, safeHttpUrl } from '@/lib/safeUrl';

export function ActivityDetailPage() {
  const { t, i18n } = useTranslation();
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const tr = useCallback((key: string): string => {
    const value = t(key);
    if (typeof value === 'string') return value;
    if (value === null || value === undefined) return '';
    try {
      return JSON.stringify(value);
    } catch {
      return String(value);
    }
  }, [t]);

  const renderText = useCallback((value: unknown): string => {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    try {
      return JSON.stringify(value);
    } catch {
      return String(value);
    }
  }, []);

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

  const { data: favoriteIds } = useQuery({
    queryKey: ['favorite-ids'],
    queryFn: () => getFavoriteIds(),
    enabled: !!user,
  });

  const { data: favorites } = useQuery({
    queryKey: ['favorites'],
    queryFn: () => getFavorites(),
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

  const updateFavoriteMutation = useMutation({
    mutationFn: ({ activityId, data }: { activityId: string; data: { notifyOnChange?: boolean } }) =>
      updateFavorite(activityId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
    },
  });

  const activity = (data?.data || null) as ActivityDetail | null;
  const isFavorite = id ? favoriteIds?.includes(id) : false;
  const currentFavorite = id ? favorites?.find((item) => item.activity_id === id) : undefined;
  const isNotifying = currentFavorite ? Boolean(currentFavorite.notify_on_change) : false;
  const toggleFavorite = () => {
    if (!user || !id) return;
    if (isFavorite) {
      removeFavoriteMutation.mutate(id);
    } else {
      addFavoriteMutation.mutate(id);
    }
  };

  const toggleNotify = () => {
    if (!user || !id) return;
    if (!isFavorite) {
      addFavoriteMutation.mutate(id, {
        onSuccess: () => {
          updateFavoriteMutation.mutate({ activityId: id, data: { notifyOnChange: true } });
        },
      });
      return;
    }
    updateFavoriteMutation.mutate({ activityId: id, data: { notifyOnChange: !isNotifying } });
  };

  useEffect(() => {
    if (typeof document === 'undefined') return;
    if (!activity) return;

    const descriptionText = renderText(activity.description);
    const description = descriptionText ? descriptionText.slice(0, 155) : tr('meta.defaultDescription');

    const titleText = renderText(activity.title);
    document.title = titleText ? `${titleText} · ${tr('meta.siteName')}` : tr('meta.siteName');

    const metaDescription = document.querySelector('meta[name="description"]') as HTMLMetaElement | null;
    if (metaDescription) {
      metaDescription.content = description;
    } else {
      const meta = document.createElement('meta');
      meta.name = 'description';
      meta.content = description;
      document.head.appendChild(meta);
    }
  }, [activity, renderText, tr]);

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center py-20">
        <div className="relative">
          <div className="absolute inset-0 bg-primary/20 rounded-full animate-ping" />
          <Loader2 className="relative w-12 h-12 animate-spin text-primary" />
        </div>
        <p className="mt-4 text-muted-foreground font-medium">{tr('activity.loading')}</p>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="max-w-2xl mx-auto">
        <Link
          to="/"
          className="inline-flex items-center gap-2 text-muted-foreground hover:text-primary transition-colors mb-6"
        >
          <ArrowLeft className="w-4 h-4" />
          {tr('activity.backToList')}
        </Link>
        <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-8 text-center">
          <div className="w-12 h-12 bg-destructive/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <span className="text-destructive font-bold">!</span>
          </div>
          <p className="text-destructive font-semibold text-lg mb-2">{tr('activity.loadError')}</p>
          <p className="text-sm text-muted-foreground">{error.message}</p>
        </div>
      </div>
    );
  }

  if (!data || !data.data || typeof data.data !== 'object') {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="bg-muted/30 border border-dashed border-muted-foreground/30 rounded-xl p-8 text-center">
          <p className="text-muted-foreground font-semibold mb-2">{tr('activity.loadError')}</p>
          <p className="text-sm text-muted-foreground">{tr('activities.noResults')}</p>
        </div>
      </div>
    );
  }

  if (!activity) {
    return (
      <div className="max-w-2xl mx-auto">
        <div className="bg-muted/30 border border-dashed border-muted-foreground/30 rounded-xl p-8 text-center">
          <p className="text-muted-foreground font-semibold mb-2">{tr('activity.loadError')}</p>
          <p className="text-sm text-muted-foreground">{tr('activities.noResults')}</p>
        </div>
      </div>
    );
  }

  const staff: Staff | null = activity.staff;
  const pricingTable: PricingTable[] | null = activity.pricingTable;
  const locationDetails: LocationDetails | null = activity.locationDetails;
  const scheduleDetails: ScheduleDetails | null = activity.scheduleDetails;
  const similarActivities: SimilarActivity[] = (similarData?.data || []) as SimilarActivity[];
  const sessions = activity.sessions as Array<{
    date?: string | null;
    timeStart?: string | null;
    timeEnd?: string | null;
    title?: string | null;
    description?: string | null;
  }> | null;

  const localeCode =
    i18n.language.startsWith('en') ? 'en-US' :
    i18n.language.startsWith('ca') || i18n.language.startsWith('val') ? 'ca-ES' :
    i18n.language.startsWith('eu') ? 'eu-ES' :
    i18n.language.startsWith('gl') ? 'gl-ES' :
    'es-ES';

  const toStringOrNull = (value: unknown): string | null => {
    if (typeof value === 'string' && value.trim() !== '') return value;
    return null;
  };

  const formatCredits = (credits: number) => {
    if (!Number.isFinite(credits)) return '—';
    const value = credits / 100;
    const decimals = value % 1 === 0 ? 0 : value % 0.1 === 0 ? 1 : 2;
    return value.toLocaleString(localeCode, { minimumFractionDigits: decimals, maximumFractionDigits: 2 });
  };

  const formatPrice = (amount: number, currency?: string | null) => {
    if (!Number.isFinite(amount)) return '—';
    const value = amount / 100;
    const symbol = currency === 'USD' ? '$' : currency === 'GBP' ? '£' : '€';
    return `${value.toLocaleString(localeCode, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${symbol}`;
  };

  // Harvested fields are never trusted at render time (audit hardening):
  // only http(s) URLs are renderable, mailto:/tel: only for contact hrefs.
  const imageUrl = safeHttpUrl(toStringOrNull(activity.imageUrl));
  const activityUrl = safeHttpUrl(toStringOrNull(activity.url));
  const enrollmentLink = safeHref(toStringOrNull(activity.enrollmentLink));
  const startDate = toStringOrNull(activity.startDate);
  const endDate = toStringOrNull(activity.endDate);
  const updatedAt = toStringOrNull(activity.updatedAt);
  const requirements = activity.requirements && typeof activity.requirements === 'object'
    ? activity.requirements as Record<string, unknown>
    : null;
  const requirementValue = (key: string): unknown => (requirements ? requirements[key] : null);
  const calendarUrl = safeHttpUrl(toStringOrNull(requirementValue('calendarUrl')));
  const contactInfo = requirementValue('contact');
  const contactText = typeof contactInfo === 'object' && contactInfo !== null
    ? toStringOrNull((contactInfo as Record<string, unknown>).text)
    : null;
  const contactEmail = typeof contactInfo === 'object' && contactInfo !== null
    ? toStringOrNull((contactInfo as Record<string, unknown>).email)
    : null;
  const contactPhone = typeof contactInfo === 'object' && contactInfo !== null
    ? toStringOrNull((contactInfo as Record<string, unknown>).phone)
    : null;
  const qualification = toStringOrNull(requirementValue('qualification'));
  const objectives = toStringOrNull(requirementValue('objectives'));
  const methodology = toStringOrNull(requirementValue('methodology'));
  const assistance = toStringOrNull(requirementValue('assistance'));
  const virtualAssistance = toStringOrNull(requirementValue('virtualAssistance'));
  const collaborators = toStringOrNull(requirementValue('collaborators'));

  const formatDisplayDate = (value?: string | null) => {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(localeCode, { dateStyle: 'long' }).format(date);
  };

  const formatDisplayDateTime = (value?: string | null) => {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat(localeCode, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  };

  return (
    <div className="max-w-4xl mx-auto">
      <Link
        to="/"
        className="inline-flex items-center gap-2 text-muted-foreground hover:text-primary transition-colors mb-6 group"
      >
        <ArrowLeft className="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
        <span className="font-medium">{tr('activity.backToList')}</span>
      </Link>

      <div className={`relative rounded-t-2xl overflow-hidden ${imageUrl ? 'bg-black' : 'bg-gradient-to-br from-primary via-primary/95 to-primary/90'}`}>
        <div className={`absolute inset-0 opacity-10 ${imageUrl ? 'hidden' : ''}`}>
          <div className="absolute top-0 right-0 w-64 h-64 bg-white/20 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
          <div className="absolute bottom-0 left-0 w-48 h-48 bg-white/10 rounded-full blur-2xl translate-y-1/2 -translate-x-1/2" />
        </div>
        {imageUrl && (
          <>
            <img
              src={imageUrl}
              alt={renderText(activity.title) || 'Imagen de la actividad'}
              className="absolute inset-0 w-full h-full object-cover"
              loading="lazy"
            />
            <div className="absolute inset-0 bg-black/45" />
          </>
        )}

        <div className="relative p-6 lg:p-8">
          <div className="flex items-start justify-between gap-4 mb-6">
            <h1 className="text-2xl lg:text-3xl font-bold text-white leading-tight">
              {renderText(activity.title) || tr('activity.noTitle')}
            </h1>
            {user && (
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={toggleNotify}
                  aria-pressed={isNotifying}
                  className={`flex items-center justify-center w-10 h-10 rounded-full border transition-colors ${
                    isNotifying
                      ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800'
                      : 'bg-white/15 text-white border-white/30 hover:bg-white/25'
                  }`}
                  title={isNotifying ? tr('activity.notifyOn') : tr('activity.notifyOff')}
                >
                  {isNotifying ? <Bell className="w-4 h-4" /> : <BellOff className="w-4 h-4" />}
                </button>
                <button
                  type="button"
                  onClick={toggleFavorite}
                  aria-pressed={isFavorite}
                  className={`flex items-center justify-center w-10 h-10 rounded-full border transition-colors ${
                    isFavorite
                      ? 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800'
                      : 'bg-white/15 text-white border-white/30 hover:bg-white/25'
                  }`}
                >
                  <Star className={`w-4 h-4 ${isFavorite ? 'fill-current' : ''}`} />
                </button>
              </div>
            )}
          </div>

          <div className="flex flex-wrap gap-2">
            {activity.modality && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30">
                {activity.modality === 'online' ? `🌐 ${tr('activity.modalityOnline')}` :
                 activity.modality === 'in-person' ? `🏛️ ${tr('activity.modalityInPerson')}` :
                 activity.modality === 'hybrid' ? `🔄 ${tr('activity.modalityHybrid')}` :
                 renderText(activity.modality)}
              </span>
            )}
            {activity.center && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30 flex items-center gap-1.5">
                <MapPin className="w-3.5 h-3.5" />
                {renderText(activity.center)}
              </span>
            )}
            {activity.typology && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30">
                {renderText(activity.typology)}
              </span>
            )}
            {activity.area && (
              <span className="px-4 py-1.5 bg-white/20 backdrop-blur-sm text-white rounded-full text-sm font-medium border border-white/30">
                {renderText(activity.area)}
              </span>
            )}
          </div>
        </div>
      </div>

      <article className="bg-card border border-t-0 border-border rounded-b-2xl shadow-sm overflow-hidden">
        <div className="p-6 lg:p-8 space-y-8">
          {activity.description && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3 flex items-center gap-2">
                <BookOpen className="w-5 h-5 text-primary" />
                {tr('activity.description')}
              </h2>
              <p className="text-muted-foreground whitespace-pre-wrap leading-relaxed">
                {renderText(activity.description)}
              </p>
            </section>
          )}

          <section>
            <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
              <Calendar className="w-5 h-5 text-primary" />
              {tr('activity.schedule')}
            </h2>
            <div className="space-y-4">
              <div className="grid md:grid-cols-2 gap-4">
                {startDate && (
                  <div className="bg-gradient-to-br from-primary/5 to-primary/10 border border-primary/20 rounded-xl p-5">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-primary/20 rounded-lg flex items-center justify-center">
                        <Calendar className="w-5 h-5 text-primary" />
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium">{tr('activity.start')}</p>
                        <p className="font-semibold text-foreground">{formatDisplayDate(startDate) ?? startDate}</p>
                      </div>
                    </div>
                  </div>
                )}
                {endDate && (
                  <div className="bg-gradient-to-br from-accent/5 to-accent/10 border border-accent/20 rounded-xl p-5">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-accent/20 rounded-lg flex items-center justify-center">
                        <Calendar className="w-5 h-5 text-accent" />
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium">{tr('activity.end')}</p>
                        <p className="font-semibold text-foreground">{formatDisplayDate(endDate) ?? endDate}</p>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {scheduleDetails && (scheduleDetails.timeStart || scheduleDetails.timeEnd) && (
                <div className="bg-muted/30 rounded-xl p-5 border border-border">
                  <div className="flex items-center gap-3">
                    <Clock className="w-5 h-5 text-primary" />
                    <div>
                      <p className="text-sm text-muted-foreground">{tr('activity.scheduleLabel')}</p>
                      <p className="font-semibold text-foreground">
                        {scheduleDetails.timeStart && scheduleDetails.timeEnd
                          ? `De ${renderText(scheduleDetails.timeStart)} a ${renderText(scheduleDetails.timeEnd)} h.`
                          : renderText(scheduleDetails.timeStart || scheduleDetails.timeEnd)}
                        {scheduleDetails.timezone && ` (${renderText(scheduleDetails.timezone)})`}
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {locationDetails && locationDetails.venue && (
                <div className="bg-muted/30 rounded-xl p-5 border border-border">
                  <div className="flex items-start gap-3">
                    <MapPin className="w-5 h-5 text-primary mt-0.5" />
                    <div>
                      <p className="text-sm text-muted-foreground">{tr('activity.location')}</p>
                      <p className="font-semibold text-foreground">{renderText(locationDetails.venue)}</p>
                      {locationDetails.center && <p className="text-sm text-muted-foreground mt-1">{renderText(locationDetails.center)}</p>}
                      {locationDetails.address && <p className="text-sm text-muted-foreground mt-1">{renderText(locationDetails.address)}</p>}
                      {locationDetails.city && <p className="text-sm text-muted-foreground mt-1">{renderText(locationDetails.city)}</p>}
                    </div>
                  </div>
                </div>
              )}

              {calendarUrl && (
                <div className="bg-muted/30 rounded-xl p-5 border border-border">
                  <a
                    href={calendarUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primary/80"
                  >
                    {tr('activity.viewCalendar')}
                    <ExternalLink className="w-4 h-4" />
                  </a>
                </div>
              )}
            </div>
          </section>

          {sessions && sessions.length > 0 && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <Clock className="w-5 h-5 text-primary" />
                {tr('activity.program')}
              </h2>
              <div className="space-y-3">
                {sessions.map((session, index) => (
                  <div key={index} className="border border-border rounded-lg p-4 bg-muted/20">
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                      {session.date && <span>{renderText(session.date)}</span>}
                      {session.timeStart && session.timeEnd && (
                        <span>{renderText(session.timeStart)}–{renderText(session.timeEnd)}</span>
                      )}
                    </div>
                    {session.title && (
                      <p className="mt-2 font-medium text-foreground">{renderText(session.title)}</p>
                    )}
                    {session.description && (
                      <p className="mt-1 text-sm text-muted-foreground">{renderText(session.description)}</p>
                    )}
                  </div>
                ))}
              </div>
            </section>
          )}

          {pricingTable && pricingTable.length > 0 ? (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <GraduationCap className="w-5 h-5 text-primary" />
                {tr('activity.prices')}
              </h2>
              <div className="flex flex-wrap items-center gap-3 mb-4">
                {activity.enrollmentOpen !== null && (
                  <div
                    className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border ${
                      activity.enrollmentOpen
                        ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800'
                        : 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
                    }`}
                  >
                    {activity.enrollmentOpen ? (
                      <>
                        <CheckCircle className="w-3.5 h-3.5" />
                        {tr('activity.enrollmentOpen')}
                      </>
                    ) : (
                      <>
                        <XCircle className="w-3.5 h-3.5" />
                        {tr('activity.enrollmentClosed')}
                      </>
                    )}
                  </div>
                )}
              </div>
              <div className="bg-gradient-to-br from-primary/5 via-primary/[0.03] to-transparent border-2 border-primary/20 rounded-2xl overflow-hidden">
                <table className="w-full">
                  <thead className="bg-primary/10">
                    <tr>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">{tr('activity.modality')}</th>
                      <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">{tr('activity.type')}</th>
                      <th className="px-4 py-3 text-right text-sm font-semibold text-foreground">{tr('activity.price')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pricingTable.map((price, index) => {
                      const amount = typeof price.amount === 'number' ? price.amount : null;
                      return (
                        <tr key={index} className={index !== pricingTable.length - 1 ? 'border-t border-primary/10' : ''}>
                          <td className="px-4 py-3 text-sm text-foreground">
                            {price.modalityLabel
                              ? renderText(price.modalityLabel)
                              : price.modality
                                ? renderText(price.modality).replace(/_/g, ' ')
                                : '—'}
                          </td>
                          <td className="px-4 py-3 text-sm text-muted-foreground">{renderText(price.studentType)}</td>
                          <td className="px-4 py-3 text-right font-semibold text-primary">
                            {amount === 0
                              ? tr('activity.free')
                              : amount !== null
                                ? formatPrice(amount, price.currency)
                                : (price.display ? renderText(price.display) : '—')}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </section>
          ) : (
            <section className="bg-gradient-to-br from-primary/5 via-primary/[0.03] to-transparent border-2 border-primary/20 rounded-2xl p-6">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                  <p className="text-sm text-muted-foreground mb-1 flex items-center gap-1.5">
                    <span className="w-1.5 h-1.5 bg-primary rounded-full animate-pulse" />
                    {tr('activity.price')}
                  </p>
                  {activity.priceDisplay !== null ? (
                    <p className="text-3xl font-bold text-primary">{renderText(activity.priceDisplay)}</p>
                  ) : activity.isFree ? (
                    <p className="text-2xl font-bold text-green-600">{tr('activity.free')}</p>
                  ) : (
                    <p className="text-lg text-muted-foreground italic">{tr('activity.priceConsult')}</p>
                  )}
                </div>
                <div className="flex flex-wrap items-center gap-3">
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
                          {tr('activity.enrollmentOpen')}
                        </>
                      ) : (
                        <>
                          <XCircle className="w-4 h-4" />
                          {tr('activity.enrollmentClosed')}
                        </>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </section>
          )}

          {staff && (staff.director || (staff.speakers && staff.speakers.length > 0)) && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <Users className="w-5 h-5 text-primary" />
                {tr('activity.team')}
              </h2>
              <div className="space-y-4">
                {staff.director && (
                  <div className="bg-muted/30 rounded-xl p-4 border border-border">
                    <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium mb-1">{tr('activity.coordinator')}</p>
                    <p className="font-semibold text-foreground">{renderText(staff.director.name)}</p>
                    {staff.director.role && <p className="text-sm text-muted-foreground mt-1">{renderText(staff.director.role)}</p>}
                  </div>
                )}
                {staff.speakers && staff.speakers.length > 0 && (
                  <div className="bg-muted/30 rounded-xl p-4 border border-border">
                    <p className="text-xs text-muted-foreground uppercase tracking-wide font-medium mb-3">{tr('activity.speakers')}</p>
                    <div className="space-y-3">
                      {staff.speakers.map((speaker, index) => (
                        <div key={index}>
                          <p className="font-semibold text-foreground">{renderText(speaker.name)}</p>
                          {speaker.role && <p className="text-sm text-muted-foreground">{renderText(speaker.role)}</p>}
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </section>
          )}

          {activity.targetAudience && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.targetAudience')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(activity.targetAudience)}</p>
            </section>
          )}

          {qualification && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.qualification')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(qualification)}</p>
            </section>
          )}

          {objectives && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.objectives')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(objectives)}</p>
            </section>
          )}

          {methodology && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.methodology')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(methodology)}</p>
            </section>
          )}

          {assistance && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.assistance')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(assistance)}</p>
            </section>
          )}

          {virtualAssistance && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.virtualAssistance')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(virtualAssistance)}</p>
            </section>
          )}

          {collaborators && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.collaborators')}</h2>
              <p className="text-muted-foreground leading-relaxed">{renderText(collaborators)}</p>
            </section>
          )}

          {(contactText || contactEmail || contactPhone) && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-3">{tr('activity.moreInfo')}</h2>
              {contactText && (
                <div className="space-y-1 text-muted-foreground leading-relaxed">
                  {renderText(contactText).split('\n').map((line, index) => (
                    <p key={index}>{line}</p>
                  ))}
                </div>
              )}
              {(contactEmail || contactPhone) && (
                <div className="mt-3 space-y-1 text-sm text-muted-foreground">
                  {contactEmail && (
                    <p>
                      {tr('activity.contactEmail')}: <a className="text-primary hover:underline" href={`mailto:${contactEmail}`}>{contactEmail}</a>
                    </p>
                  )}
                  {contactPhone && (
                    <p>
                      {tr('activity.contactPhone')}: <a className="text-primary hover:underline" href={`tel:${contactPhone}`}>{contactPhone}</a>
                    </p>
                  )}
                </div>
              )}
            </section>
          )}

          {similarActivities.length > 0 && (
            <section>
              <h2 className="text-lg font-semibold text-foreground mb-4 flex items-center gap-2">
                <Users className="w-5 h-5 text-primary" />
                {tr('activity.similar')}
              </h2>
              <div className="grid gap-4 grid-cols-1">
                {similarActivities.map((similar) => (
                  <ActivityCard key={similar.id} activity={similar} />
                ))}
              </div>
            </section>
          )}

          <footer className="pt-6 border-t border-border">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-sm">
              <div className="space-y-1">
                <p className="text-muted-foreground">
                  {tr('activity.unedCode')}: <span className="font-mono font-medium text-foreground">{renderText(activity.unedId)}</span>
                </p>
                {activity.credits !== null && activity.credits !== undefined && (
                  <p className="text-muted-foreground">
                    {tr('activity.credits')}: <span className="font-medium text-foreground">{formatCredits(activity.credits)}</span>
                  </p>
                )}
                {updatedAt && (
                  <p className="text-muted-foreground">
                    {tr('activity.updated')}: <span className="text-foreground">{formatDisplayDateTime(updatedAt) ?? updatedAt}</span>
                  </p>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-3 sm:justify-end">
                {activityUrl && (
                  <a
                    href={activityUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground rounded-lg font-medium hover:bg-primary/90 transition-colors"
                  >
                    {tr('activity.viewOnUned')}
                    <ExternalLink className="w-4 h-4" />
                  </a>
                )}
                {enrollmentLink && (
                  <a
                    href={enrollmentLink}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded-lg font-medium hover:bg-emerald-700 transition-colors"
                  >
                    {tr('activity.enrollmentGo')}
                    <ExternalLink className="w-4 h-4" />
                  </a>
                )}
              </div>
            </div>
          </footer>
        </div>
      </article>
    </div>
  );
}
