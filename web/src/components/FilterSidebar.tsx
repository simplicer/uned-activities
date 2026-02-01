/**
 * Filter Sidebar component.
 */

import { type ActivityFilters } from '@/lib/api/activities';
import { X, Filter } from 'lucide-react';
import { useState } from 'react';

interface FilterSidebarProps {
  filters: ActivityFilters;
  onFiltersChange: (filters: ActivityFilters) => void;
  isOpen: boolean;
  onToggle: () => void;
}

const MODALITY_OPTIONS = [
  { value: 'online', label: 'Online' },
  { value: 'in-person', label: 'Presencial' },
  { value: 'hybrid', label: 'Híbrido' },
];

export function FilterSidebar({ filters, onFiltersChange, isOpen, onToggle }: FilterSidebarProps) {
  const [localFilters, setLocalFilters] = useState<ActivityFilters>(filters);

  const handleChange = (key: keyof ActivityFilters, value: string | number | undefined) => {
    setLocalFilters((prev) => ({
      ...prev,
      [key]: value || undefined,
    }));
  };

  const handleApply = () => {
    onFiltersChange(localFilters);
  };

  const handleClear = () => {
    const cleared = {} as ActivityFilters;
    setLocalFilters(cleared);
    onFiltersChange(cleared);
  };

  const hasActiveFilters = Object.keys(filters).length > 0 && (
    filters.center ||
    filters.typology ||
    filters.area ||
    filters.modality ||
    filters.minPrice !== undefined ||
    filters.maxPrice !== undefined ||
    filters.search
  );

  return (
    <>
      {/* Mobile toggle */}
      <button
        onClick={onToggle}
        className="lg:hidden fixed bottom-4 right-4 z-40 bg-primary text-primary-foreground p-3 rounded-full shadow-lg"
      >
        <Filter className="w-6 h-6" />
      </button>

      {/* Sidebar */}
      <aside
        className={`
          fixed lg:sticky top-0 left-0 h-screen w-80 bg-background border-r overflow-y-auto z-30
          transform transition-transform duration-200 ease-in-out
          ${isOpen ? 'translate-x-0' : '-translate-x-full'}
          lg:translate-x-0
        `}
      >
        <div className="p-4">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-semibold">Filtros</h2>
            {hasActiveFilters && (
              <button
                onClick={handleClear}
                className="text-sm text-muted-foreground hover:text-foreground flex items-center gap-1"
              >
                <X className="w-4 h-4" />
                Limpiar
              </button>
            )}
          </div>

          <div className="space-y-6">
            {/* Search */}
            <div>
              <label className="block text-sm font-medium mb-2">Buscar</label>
              <input
                type="text"
                value={localFilters.search || ''}
                onChange={(e) => handleChange('search', e.target.value)}
                placeholder="Título o descripción..."
                className="w-full px-3 py-2 border rounded-md bg-background"
              />
            </div>

            {/* Center */}
            <div>
              <label className="block text-sm font-medium mb-2">Centro</label>
              <input
                type="text"
                value={localFilters.center || ''}
                onChange={(e) => handleChange('center', e.target.value)}
                placeholder="Ej: Madrid"
                className="w-full px-3 py-2 border rounded-md bg-background"
              />
            </div>

            {/* Modality */}
            <div>
              <label className="block text-sm font-medium mb-2">Modalidad</label>
              <div className="space-y-2">
                {MODALITY_OPTIONS.map((option) => (
                  <label key={option.value} className="flex items-center gap-2">
                    <input
                      type="radio"
                      name="modality"
                      value={option.value}
                      checked={localFilters.modality === option.value}
                      onChange={(e) => handleChange('modality', e.target.value)}
                      className="w-4 h-4"
                    />
                    <span className="text-sm">{option.label}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Price range */}
            <div>
              <label className="block text-sm font-medium mb-2">Precio (€)</label>
              <div className="flex gap-2 items-center">
                <input
                  type="number"
                  value={localFilters.minPrice || ''}
                  onChange={(e) => handleChange('minPrice', e.target.value ? Number(e.target.value) : undefined)}
                  placeholder="Mín"
                  className="w-full px-3 py-2 border rounded-md bg-background"
                  min="0"
                  step="10"
                />
                <span className="text-muted-foreground">-</span>
                <input
                  type="number"
                  value={localFilters.maxPrice || ''}
                  onChange={(e) => handleChange('maxPrice', e.target.value ? Number(e.target.value) : undefined)}
                  placeholder="Máx"
                  className="w-full px-3 py-2 border rounded-md bg-background"
                  min="0"
                  step="10"
                />
              </div>
            </div>

            {/* Apply button for mobile */}
            <button
              onClick={handleApply}
              className="w-full lg:hidden bg-primary text-primary-foreground py-2 rounded-md font-medium"
            >
              Aplicar filtros
            </button>
          </div>
        </div>
      </aside>

      {/* Overlay for mobile */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-20 lg:hidden"
          onClick={onToggle}
        />
      )}
    </>
  );
}
