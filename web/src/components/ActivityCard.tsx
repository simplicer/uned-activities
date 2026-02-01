/**
 * Activity Card component.
 */

import { Activity } from '@/lib/api/activities';
import { Link } from 'react-router-dom';

interface ActivityCardProps {
  activity: Activity;
}

const MODALITY_LABELS: Record<string, string> = {
  'online': 'Online',
  'in-person': 'Presencial',
  'hybrid': 'Híbrido',
};

export function ActivityCard({ activity }: ActivityCardProps) {
  return (
    <Link to={`/activities/${activity.id}`} className="block">
      <article className="bg-card border rounded-lg p-4 hover:shadow-md transition-shadow">
        <h3 className="font-semibold text-lg mb-2 line-clamp-2">
          {activity.title || 'Sin título'}
        </h3>

        <div className="flex flex-wrap gap-2 mb-3">
          {activity.modality && (
            <span className="px-2 py-1 bg-primary/10 text-primary text-xs rounded-full">
              {MODALITY_LABELS[activity.modality] || activity.modality}
            </span>
          )}
          {activity.center && (
            <span className="px-2 py-1 bg-muted text-muted-foreground text-xs rounded-full">
              {activity.center}
            </span>
          )}
          {activity.typology && (
            <span className="px-2 py-1 bg-secondary/50 text-secondary-foreground text-xs rounded-full">
              {activity.typology}
            </span>
          )}
        </div>

        {activity.description && (
          <p className="text-sm text-muted-foreground line-clamp-2 mb-3">
            {activity.description}
          </p>
        )}

        <div className="flex items-center justify-between text-sm">
          {activity.priceDisplay !== null && (
            <span className="font-semibold text-primary">
              {activity.priceDisplay}
            </span>
          )}
          {activity.startDate && (
            <span className="text-muted-foreground">
              Inicio: {new Date(activity.startDate).toLocaleDateString('es-ES')}
            </span>
          )}
        </div>

        {activity.enrollmentOpen !== null && (
          <div className="mt-3 pt-3 border-t">
            <span
              className={`text-xs font-medium ${
                activity.enrollmentOpen
                  ? 'text-green-600 bg-green-50'
                  : 'text-gray-600 bg-gray-50'
              } px-2 py-1 rounded`}
            >
              {activity.enrollmentOpen ? '✓ Matrícula abierta' : '✗ Matrícula cerrada'}
            </span>
          </div>
        )}
      </article>
    </Link>
  );
}
