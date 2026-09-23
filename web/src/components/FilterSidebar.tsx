/**
 * Filter Sidebar component.
 */

import { type ActivityFilters, getCenters, type CenterOption } from '@/lib/api/activities';
import { centerCommunityMap, communityOrder } from '@/data/centerCommunities';
import { X, Filter, SlidersHorizontal, ChevronDown, ChevronUp, Search, MapPin, Video, GraduationCap, Gift, CalendarDays, TicketCheck } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
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

  const { data: centersData } = useQuery({
    queryKey: ['centers'],
    queryFn: getCenters,
  });

  const centers = centersData?.data;

  const modalityOnlineChecked =
    localFilters.modality === 'online' || localFilters.modality === 'hybrid';
  const modalityInPersonChecked =
    localFilters.modality === 'in-person' || localFilters.modality === 'hybrid';

  const groupedCenters = useMemo(() => {
    const groups = new Map<string, CenterOption[]>();
    const fallback = t('filters.centerOther');

    (centers ?? []).forEach((center) => {
      const community = centerCommunityMap[center.name] || fallback;
      if (!groups.has(community)) {
        groups.set(community, []);
      }
      groups.get(community)!.push(center);
    });

    for (const [, group] of groups) {
      group.sort((a, b) => a.name.localeCompare(b.name, 'es'));
    }

    const ordered: Array<[string, CenterOption[]]> = [];
    communityOrder.forEach((community) => {
      const group = groups.get(community);
      if (group) {
        ordered.push([community, group]);
        groups.delete(community);
      }
    });

    const remaining = Array.from(groups.entries()).sort((a, b) => a[0].localeCompare(b[0], 'es'));
    return ordered.concat(remaining);
  }, [centers, t]);

  const handleChange = (key: keyof ActivityFilters, value: string | number | boolean | undefined) => {
    let nextFilters: ActivityFilters | null = null;

    setLocalFilters((prev) => {
      const nextValue = value || undefined;
      const next = { ...prev, [key]: nextValue };

      if (key === 'deliveryMode' && (nextValue === undefined || nextValue === '')) {
        next.deliveryMode = undefined;
      }

      nextFilters = next;
      return next;
    });

    if (key !== 'search' && nextFilters) {
      onFiltersChange(nextFilters);
    }
  };

  const handleSearchApply = () => {
    onFiltersChange({ ...filters, search: localFilters.search || undefined });
  };

  const handleModalityToggle = (key: 'online' | 'in-person') => {
    let nextFilters: ActivityFilters | null = null;

    setLocalFilters((prev) => {
      const isOnline = prev.modality === 'online' || prev.modality === 'hybrid';
      const isInPerson = prev.modality === 'in-person' || prev.modality === 'hybrid';

      const nextOnline = key === 'online' ? !isOnline : isOnline;
      const nextInPerson = key === 'in-person' ? !isInPerson : isInPerson;

      let nextModality: ActivityFilters['modality'] = undefined;
      if (nextOnline && nextInPerson) {
        nextModality = 'hybrid';
      } else if (nextOnline) {
        nextModality = 'online';
      } else if (nextInPerson) {
        nextModality = 'in-person';
      }

      const next = { ...prev, modality: nextModality };
      if (!nextOnline && next.deliveryMode) {
        next.deliveryMode = undefined;
      }

      nextFilters = next;
      return next;
    });

    if (nextFilters) {
      onFiltersChange(nextFilters);
    }
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
      ['freeOnly', filters.freeOnly],
      ['deliveryMode', filters.deliveryMode],
      ['withCredits', filters.withCredits],
      ['search', filters.search],
      ['startDateFrom', filters.startDateFrom],
      ['startDateTo', filters.startDateTo],
      ['enrollmentOpenOnly', filters.enrollmentOpenOnly],
      ['sort', filters.sort && filters.sort !== 'cercania' ? filters.sort : undefined],
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
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      handleSearchApply();
                    }
                  }}
                  placeholder={t('filters.searchPlaceholder')}
                  className="input pl-10"
                />
              </div>
              <button
                onClick={handleSearchApply}
                className="mt-2 w-full btn-primary"
              >
                {t('filters.searchApply')}
              </button>
            </FilterSection>

            {/* Modality */}
            <FilterSection title={t('filters.modality')} icon={<SlidersHorizontal className="w-4 h-4 text-muted-foreground" />}>
              <div className="space-y-2">
                <label
                  className={`
                    flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
                    ${modalityOnlineChecked
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-primary/30 hover:bg-muted/30'
                    }
                  `}
                >
                  <input
                    type="checkbox"
                    checked={modalityOnlineChecked}
                    onChange={() => handleModalityToggle('online')}
                    className="w-5 h-5 text-primary focus:ring-primary focus:ring-offset-0 rounded"
                  />
                  <span className="text-lg">💻</span>
                  <span className="text-sm font-medium">{t('filters.modalityOnline')}</span>
                </label>
                <label
                  className={`
                    flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
                    ${modalityInPersonChecked
                      ? 'border-primary bg-primary/5'
                      : 'border-border hover:border-primary/30 hover:bg-muted/30'
                    }
                  `}
                >
                  <input
                    type="checkbox"
                    checked={modalityInPersonChecked}
                    onChange={() => handleModalityToggle('in-person')}
                    className="w-5 h-5 text-primary focus:ring-primary focus:ring-offset-0 rounded"
                  />
                  <span className="text-lg">🏛️</span>
                  <span className="text-sm font-medium">{t('filters.modalityInPerson')}</span>
                </label>
              </div>
              <p className="mt-2 text-xs text-muted-foreground">
                {t('filters.modalityHint')}
              </p>
            </FilterSection>

            {/* Center */}
            <FilterSection title={t('filters.center')} icon={<MapPin className="w-4 h-4 text-muted-foreground" />}>
              <select
                value={localFilters.center || ''}
                onChange={(e) => handleChange('center', e.target.value || undefined)}
                className="input"
              >
                <option value="">{t('filters.centerAll')}</option>
                {groupedCenters.map(([community, options]) => (
                  <optgroup key={community} label={community}>
                    {options.map((center) => (
                      <option key={center.name} value={center.name}>
                        {center.name} ({center.count})
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </FilterSection>

            {/* Fechas: presets rápidos + rango con calendario */}
            <FilterSection title={t('filters.dates')} icon={<CalendarDays className="w-4 h-4 text-muted-foreground" />}>
              <div className="flex flex-wrap gap-2 mb-3">
                {[
                  { key: 'week', labelKey: 'filters.presetWeek', days: 7 },
                  { key: 'month', labelKey: 'filters.presetMonth', days: 30 },
                  { key: 'quarter', labelKey: 'filters.presetThreeMonths', days: 90 },
                ].map((preset) => (
                  <button
                    key={preset.key}
                    type="button"
                    onClick={() => {
                      const from = new Date();
                      const to = new Date();
                      to.setDate(to.getDate() + preset.days);
                      const iso = (d: Date) => d.toISOString().slice(0, 10);
                      const next = { ...localFilters, startDateFrom: iso(from), startDateTo: iso(to) };
                      setLocalFilters(next);
                      onFiltersChange(next);
                    }}
                    className="text-xs px-3 py-1.5 rounded-full border border-border hover:border-primary hover:text-primary transition-colors"
                  >
                    {t(preset.labelKey)}
                  </button>
                ))}
              </div>
              <label className="block text-xs text-muted-foreground mb-1">{t('filters.dateFrom')}</label>
              <input
                type="date"
                value={localFilters.startDateFrom || ''}
                onChange={(e) => handleChange('startDateFrom', e.target.value)}
                className="w-full text-sm rounded-lg border border-border bg-background px-3 py-2 mb-3"
              />
              <label className="block text-xs text-muted-foreground mb-1">{t('filters.dateTo')}</label>
              <input
                type="date"
                value={localFilters.startDateTo || ''}
                onChange={(e) => handleChange('startDateTo', e.target.value)}
                className="w-full text-sm rounded-lg border border-border bg-background px-3 py-2"
              />
            </FilterSection>

            {/* Solo inscripciones abiertas */}
            <FilterSection title={t('filters.enrollment')} icon={<TicketCheck className="w-4 h-4 text-muted-foreground" />}>
              <label className="flex items-center gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={localFilters.enrollmentOpenOnly === true}
                  onChange={(e) => handleChange('enrollmentOpenOnly', e.target.checked ? true : undefined)}
                  className="w-4 h-4 accent-primary"
                />
                <span className="text-sm font-medium">{t('filters.enrollmentOpenOnly')}</span>
              </label>
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

            {/* Delivery mode - Diferido */}
            {modalityOnlineChecked ? (
              <FilterSection title={t('filters.deliveryMode')} icon={<Video className="w-4 h-4 text-muted-foreground" />}>
                <label className="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all hover:bg-muted/30">
                  <input
                    type="checkbox"
                    checked={localFilters.deliveryMode === 'recorded'}
                    onChange={(e) => handleChange('deliveryMode', e.target.checked ? 'recorded' : undefined)}
                    className="w-5 h-5 text-primary focus:ring-primary focus:ring-offset-0 rounded"
                  />
                  <span className="text-lg">📼</span>
                  <span className="text-sm font-medium">{t('filters.deliveryRecorded')}</span>
                </label>
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

          <div className="h-2" />
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
