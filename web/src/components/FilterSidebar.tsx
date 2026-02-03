/**
 * Filter Sidebar component.
 */

import { type ActivityFilters } from '@/lib/api/activities';
import { X, Filter, SlidersHorizontal, ChevronDown, ChevronUp, Search, MapPin, Video, GraduationCap, Gift } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface FilterSidebarProps {
  filters: ActivityFilters;
  onFiltersChange: (filters: ActivityFilters) => void;
  isOpen: boolean;
  onToggle: () => void;
}

function FilterSection({ title, icon, children, defaultOpen = true }: { title: string; icon: React.ReactNode; children: React.ReactNode; defaultOpen?: boolean }) {
  const [isOpen, setIsOpen] = useState(defaultOpen);

  return (
    <div className="border border-border rounded-lg overflow-hidden">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="w-full flex items-center justify-between p-3 hover:bg-muted/50 transition-colors"
      >
        <div className="flex items-center gap-2 font-medium text-sm">
          {icon}
          <span>{title}</span>
        </div>
        {isOpen ? <ChevronUp className="w-4 h-4 text-muted-foreground" /> : <ChevronDown className="w-4 h-4 text-muted-foreground" />}
      </button>
      {isOpen && (
        <div className="p-3 pt-0 border-t border-border/50">
          {children}
        </div>
      )}
    </div>
  );
}

export function FilterSidebar({ filters, onFiltersChange, isOpen, onToggle }: FilterSidebarProps) {
  const { t } = useTranslation();
  const [localFilters, setLocalFilters] = useState<ActivityFilters>(filters);

  const modalityOptions = [
    { value: '', label: t('filters.modalityAll'), icon: '🌐' },
    { value: 'online', label: t('filters.modalityOnline'), icon: '💻' },
    { value: 'in-person', label: t('filters.modalityInPerson'), icon: '🏛️' },
    { value: 'hybrid', label: t('filters.modalityHybrid') || 'Híbrida', icon: '🔄' },
  ];

  const deliveryModeOptions = [
    { value: 'live', label: t('filters.deliveryLive'), icon: '📡' },
    { value: 'recorded', label: t('filters.deliveryRecorded'), icon: '📼' },
  ];

  const handleChange = (key: keyof ActivityFilters, value: string | number | boolean | undefined) => {
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

  useEffect(() => {
    setLocalFilters(filters);
  }, [filters]);

  const activeFilterCount = useMemo(() => {
    const entries: Array<[keyof ActivityFilters, unknown]> = [
      ['center', filters.center],
      ['typology', filters.typology],
      ['area', filters.area],
      ['modality', filters.modality],
      ['freeOnly', filters.freeOnly],
      ['deliveryMode', filters.deliveryMode],
      ['withCredits', filters.withCredits],
      ['search', filters.search],
      ['startDateFrom', filters.startDateFrom],
      ['startDateTo', filters.startDateTo],
    ];

    return entries.filter(([, value]) => value !== undefined && value !== null && value !== '').length;
  }, [filters]);

  const hasActiveFilters = activeFilterCount > 0;

  return (
    <>
      {/* Mobile toggle */}
      <button
        onClick={onToggle}
        className={`
          lg:hidden fixed bottom-6 right-6 z-40
          bg-primary text-primary-foreground p-4 rounded-full shadow-lg
          hover:bg-primary/90 hover:shadow-xl hover:scale-105
          transition-all duration-200
          ${isOpen ? 'rotate-45' : ''}
        `}
        aria-label="Toggle filters"
      >
        {isOpen ? <X className="w-6 h-6" /> : <SlidersHorizontal className="w-6 h-6" />}
      </button>

      {/* Active filter count badge on mobile */}
      {hasActiveFilters && !isOpen && (
        <span className="lg:hidden fixed bottom-6 right-16 z-40 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
          {activeFilterCount}
        </span>
      )}

      {/* Sidebar */}
      <aside
        className={`
          fixed lg:sticky top-0 left-0 h-screen w-80 bg-background border-r border-border overflow-y-auto z-30
          transform transition-transform duration-300 ease-in-out
          ${isOpen ? 'translate-x-0' : '-translate-x-full'}
          lg:translate-x-0
        `}
      >
        <div className="p-4 lg:p-6 space-y-6">
          {/* Header */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 bg-primary/10 rounded-lg flex items-center justify-center">
                <Filter className="w-4 h-4 text-primary" />
              </div>
              <h2 className="text-lg font-bold text-foreground">{t('filters.title')}</h2>
            </div>
            {hasActiveFilters && (
              <button
                onClick={handleClear}
                className="text-sm text-muted-foreground hover:text-primary transition-colors flex items-center gap-1 px-2 py-1 hover:bg-primary/5 rounded-lg"
              >
                <X className="w-3.5 h-3.5" />
                <span>{t('filters.clear')}</span>
              </button>
            )}
          </div>

          {/* Active filters indicator */}
          {hasActiveFilters && (
            <div className="bg-primary/5 border border-primary/20 rounded-lg p-3">
              <p className="text-sm text-primary font-medium">
                {t('filters.active', { count: activeFilterCount })}
              </p>
            </div>
          )}

          {/* Filter Sections */}
          <div className="space-y-4">
            {/* Search */}
            <FilterSection title={t('search')} icon={<Search className="w-4 h-4 text-muted-foreground" />}>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input
                  type="text"
                  value={localFilters.search || ''}
                  onChange={(e) => handleChange('search', e.target.value)}
                  placeholder={t('filters.searchPlaceholder')}
                  className="input pl-10"
                />
              </div>
            </FilterSection>

            {/* Center */}
            <FilterSection title={t('filters.center')} icon={<MapPin className="w-4 h-4 text-muted-foreground" />}>
              <div className="relative">
                <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input
                  type="text"
                  value={localFilters.center || ''}
                  onChange={(e) => handleChange('center', e.target.value)}
                  placeholder={t('filters.centerPlaceholder')}
                  className="input pl-10"
                />
              </div>
            </FilterSection>

            {/* Modality */}
            <FilterSection title={t('filters.modality')} icon={<SlidersHorizontal className="w-4 h-4 text-muted-foreground" />}>
              <div className="space-y-2">
                {modalityOptions.map((option) => (
                  <label
                    key={option.value}
                    className={`
                      flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
                      ${localFilters.modality === option.value
                        ? 'border-primary bg-primary/5'
                        : 'border-border hover:border-primary/30 hover:bg-muted/30'
                      }
                    `}
                  >
                    <input
                      type="radio"
                      name="modality"
                      value={option.value}
                      checked={localFilters.modality === option.value}
                      onChange={(e) => handleChange('modality', e.target.value === '' ? undefined : e.target.value)}
                      className="w-4 h-4 text-primary focus:ring-primary focus:ring-offset-0"
                    />
                    <span className="text-lg">{option.icon}</span>
                    <span className="text-sm font-medium">{option.label}</span>
                  </label>
                ))}
              </div>
            </FilterSection>

            {/* Price - Free only checkbox */}
            <FilterSection title={t('filters.price')} icon={<Gift className="w-4 h-4 text-muted-foreground" />}>
              <label className="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:bg-muted/30">
                <input
                  type="checkbox"
                  checked={localFilters.freeOnly || false}
                  onChange={(e) => handleChange('freeOnly', e.target.checked || undefined)}
                  className="w-5 h-5 text-primary focus:ring-primary focus:ring-offset-0 rounded"
                />
                <span className="text-sm font-medium">{t('filters.freeOnly')}</span>
              </label>
            </FilterSection>

            {/* Delivery mode - Directo / Diferido - Only show for online/hybrid */}
            {localFilters.modality === 'online' || localFilters.modality === 'hybrid' ? (
              <FilterSection title={t('filters.deliveryMode')} icon={<Video className="w-4 h-4 text-muted-foreground" />}>
                <div className="space-y-2">
                  {deliveryModeOptions.map((option) => (
                    <label
                      key={option.value}
                      className={`
                        flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
                        ${localFilters.deliveryMode === option.value
                          ? 'border-primary bg-primary/5'
                          : 'border-border hover:border-primary/30 hover:bg-muted/30'
                        }
                      `}
                    >
                      <input
                        type="radio"
                        name="deliveryMode"
                        value={option.value}
                        checked={localFilters.deliveryMode === option.value}
                        onChange={(e) => handleChange('deliveryMode', e.target.value)}
                        className="w-4 h-4 text-primary focus:ring-primary focus:ring-offset-0"
                      />
                      <span className="text-lg">{option.icon}</span>
                      <span className="text-sm font-medium">{option.label}</span>
                    </label>
                  ))}
                </div>
              </FilterSection>
            ) : null}

            {/* Credits (ECTS) - With/without credits */}
            <FilterSection title={t('filters.credits')} icon={<GraduationCap className="w-4 h-4 text-muted-foreground" />}>
              <label className="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:bg-muted/30">
                <input
                  type="checkbox"
                  checked={localFilters.withCredits || false}
                  onChange={(e) => handleChange('withCredits', e.target.checked || undefined)}
                  className="w-5 h-5 text-primary focus:ring-primary focus:ring-offset-0 rounded"
                />
                <span className="text-sm font-medium">{t('filters.withCredits')}</span>
              </label>
            </FilterSection>
          </div>

          {/* Apply button for mobile */}
          <div className="hidden lg:flex lg:pt-4 border-t border-border">
            <button
              onClick={handleApply}
              className="w-full btn-primary"
            >
              {t('filters.apply')}
            </button>
          </div>

          {/* Mobile apply button */}
          <button
            onClick={handleApply}
            className="lg:hidden w-full btn-primary sticky bottom-0"
          >
            {t('filters.apply')}
          </button>
        </div>
      </aside>

      {/* Overlay for mobile */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/50 backdrop-blur-sm z-20 lg:hidden transition-opacity"
          onClick={onToggle}
        />
      )}
    </>
  );
}
