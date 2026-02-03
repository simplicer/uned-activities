/**
 * Activity Card component.
 */

import { Activity } from '@/lib/api/activities';
import { Link } from 'react-router-dom';
import { MapPin, Calendar, ArrowRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface ActivityCardProps {
  activity: Activity;
}

export function ActivityCard({ activity }: ActivityCardProps) {
  const { t } = useTranslation();

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
          <div className="flex items-start justify-between gap-2 mb-3">
            <h3 className="card-title line-clamp-2 group-hover:text-primary transition-colors">
              {activity.title || t('activity.noTitle')}
            </h3>
          </div>

          {/* Badges */}
          <div className="flex flex-wrap gap-2">
            {activity.modality && (
              <span className={`badge border ${modalityColors[activity.modality] || 'bg-primary/10 text-primary border-primary/20'}`}>
                {modalityLabels[activity.modality] || activity.modality}
              </span>
            )}
            {activity.typology && (
              <span className="badge bg-secondary/50 text-secondary-foreground border border-secondary-200">
                {activity.typology}
              </span>
            )}
          </div>
        </div>

        {/* Card Content */}
        <div className="card-content pt-3 space-y-3">
          {/* Description */}
          {activity.description && (
            <p className="text-sm text-muted-foreground line-clamp-2">
              {activity.description}
            </p>
          )}

          {/* Metadata */}
          <div className="space-y-2 text-sm">
            {activity.center && (
              <div className="flex items-center gap-2 text-muted-foreground">
                <MapPin className="w-4 h-4 flex-shrink-0" />
                <span className="truncate">{activity.center}</span>
              </div>
            )}
            {activity.startDate && (
              <div className="flex items-center gap-2 text-muted-foreground">
                <Calendar className="w-4 h-4 flex-shrink-0" />
                <span>{new Date(activity.startDate).toLocaleDateString(t('filters.modalityOnline') === 'Online' ? 'en-US' : 'es-ES', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
              </div>
            )}
          </div>
        </div>

        {/* Card Footer */}
        <div className="card-footer pt-4 border-t">
          <div className="flex items-center justify-between w-full">
            {/* Price */}
            {activity.priceDisplay !== null ? (
              <div className="flex flex-col">
                <span className="text-xs text-muted-foreground">{t('activity.price')}</span>
                <span className="text-lg font-bold text-primary">
                  {activity.priceDisplay}
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

          {/* CTA Arrow */}
          <div className="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
            <ArrowRight className="w-5 h-5 text-primary" />
          </div>
        </div>
      </article>
    </Link>
  );
}
