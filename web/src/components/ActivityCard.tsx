/**
 * Activity Card component.
 */

import { Activity } from '@/lib/api/activities';
import { Link } from 'react-router-dom';
import { MapPin, Calendar } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ActivityCardProps {
  activity: Activity;
}

export function ActivityCard({ activity }: ActivityCardProps) {
  const { t, i18n } = useTranslation();

  const localeCode =
    i18n.language.startsWith('en') ? 'en-US' :
    i18n.language.startsWith('ca') || i18n.language.startsWith('val') ? 'ca-ES' :
    i18n.language.startsWith('eu') ? 'eu-ES' :
    i18n.language.startsWith('gl') ? 'gl-ES' :
    'es-ES';

  const formatPrice = (amount: number, currency?: string | null) => {
    if (!Number.isFinite(amount)) {
      return '—';
    }
    const value = amount / 100;
    const symbol = currency === 'USD' ? '$' : currency === 'GBP' ? '£' : '€';
    return `${value.toLocaleString(localeCode, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${symbol}`;
  };

  const formatCredits = (credits: number) => {
    if (!Number.isFinite(credits)) {
      return '—';
    }
    const value = credits / 100;
    const decimals = value % 1 === 0 ? 0 : value % 0.1 === 0 ? 1 : 2;
    return value.toLocaleString(localeCode, { minimumFractionDigits: decimals, maximumFractionDigits: 2 });
  };

  const renderText = (value: unknown): string => {
    if (value === null || value === undefined) return '';
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    try {
      return JSON.stringify(value);
    } catch {
      return String(value);
    }
  };

  const modalityLabels: Record<string, string> = {
    'online': t('filters.modalityOnline'),
    'in-person': t('filters.modalityInPerson'),
    'hybrid': t('filters.modalityHybrid') || 'Híbrida',
  };

  const modalityColors: Record<string, string> = {
    'online': 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
    'in-person': 'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border-purple-200 dark:border-purple-800',
    'hybrid': 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
  };

  return (
    <Link to={`/activities/${activity.id}`} className="block group">
      <article className="card hover-lift group-hover:shadow-card-hover transition-all duration-300">
        {/* Card Header */}
        <div className="card-header pb-3">
          <div className="flex items-start gap-4 mb-3">
            {activity.imageUrl && (
              <div className="flex-shrink-0 rounded-lg overflow-hidden border border-border w-28 h-20">
                <img
                  src={activity.imageUrl}
                  alt={activity.title || t('activity.noTitle')}
                  className="w-full h-full object-cover"
                  loading="lazy"
                />
              </div>
            )}
            <div className="flex-1 min-w-0">
              <h3 className="card-title line-clamp-2 group-hover:text-primary transition-colors">
                {activity.title ? renderText(activity.title) : t('activity.noTitle')}
              </h3>
            </div>
          </div>

          {/* Badges */}
          <div className="flex flex-wrap gap-2">
            {activity.modality && (
              <span className={`badge border ${modalityColors[activity.modality] || 'bg-primary/10 text-primary border-primary/20'}`}>
                {modalityLabels[activity.modality] || renderText(activity.modality)}
              </span>
            )}
            {activity.typology && (
              <span className="badge bg-secondary/60 text-secondary-foreground border border-secondary/60">
                {renderText(activity.typology)}
              </span>
            )}
            {activity.credits !== null && activity.credits !== undefined && (
              <span className="badge bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:border-emerald-800">
                🎓 {formatCredits(activity.credits)} ECTS
              </span>
            )}
          </div>
        </div>

        {/* Card Content */}
        <div className="card-content pt-3 space-y-3">
          {/* Description */}
          {activity.description && (
            <p className="text-sm text-muted-foreground line-clamp-2">
              {renderText(activity.description)}
            </p>
          )}

          {/* Metadata */}
          <div className="space-y-2 text-sm">
            {activity.center && (
              <div className="flex items-center gap-2 text-muted-foreground">
                <MapPin className="w-4 h-4 flex-shrink-0" />
                <span className="truncate">{renderText(activity.center)}</span>
              </div>
            )}
            {activity.startDate && (
              <div className="flex items-center gap-2 text-muted-foreground">
                <Calendar className="w-4 h-4 flex-shrink-0" />
                <span>
                  {(() => {
                    const date = new Date(activity.startDate ?? '');
                    if (Number.isNaN(date.getTime())) return renderText(activity.startDate);
                    return new Intl.DateTimeFormat(localeCode, { dateStyle: 'medium' }).format(date);
                  })()}
                </span>
              </div>
            )}
          </div>
        </div>

        {/* Card Footer */}
        <div className="card-footer pt-4 border-t">
          <div className="flex items-center justify-between w-full">
            {/* Price */}
            {activity.priceAmount !== null || activity.priceDisplay !== null ? (
              <div className="flex flex-col">
                <span className="text-xs text-muted-foreground">{t('activity.price')}</span>
                <span className="text-lg font-bold text-primary">
                  {activity.priceAmount !== null
                    ? formatPrice(activity.priceAmount, activity.priceCurrency)
                    : renderText(activity.priceDisplay)}
                </span>
              </div>
            ) : (
              <div className="flex flex-col">
                <span className="text-xs text-muted-foreground">{t('activity.price')}</span>
                <span className="text-sm font-medium text-muted-foreground">
                  {t('activity.priceConsult')}
                </span>
              </div>
            )}

            {/* Status Badge */}
            {activity.enrollmentOpen !== null && (
              <div
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors ${
                  activity.enrollmentOpen
                    ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800'
                    : 'bg-gray-50 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
                }`}
              >
                <div className={`w-1.5 h-1.5 rounded-full ${activity.enrollmentOpen ? 'bg-green-500 animate-pulse' : 'bg-gray-400'}`} />
                {activity.enrollmentOpen ? t('activity.enrollmentOpen') : t('activity.enrollmentClosed')}
              </div>
            )}
          </div>

        </div>
      </article>
    </Link>
  );
}
