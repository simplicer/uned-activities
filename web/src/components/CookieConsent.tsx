/**
 * Cookie Consent Banner component (GDPR compliant).
 */

import { useState, useEffect } from 'react';
import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export function CookieConsent() {
  const { t } = useTranslation();
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    // Check if user has already consented
    const consent = localStorage.getItem('cookie_consent');
    if (!consent) {
      setIsVisible(true);
    }
  }, []);

  const handleAcceptAll = () => {
    localStorage.setItem('cookie_consent', JSON.stringify({
      essential: true,
      preferences: true,
      analytics: false,
    }));
    setIsVisible(false);
  };

  const handleAcceptEssential = () => {
    localStorage.setItem('cookie_consent', JSON.stringify({
      essential: true,
      preferences: false,
      analytics: false,
    }));
    setIsVisible(false);
  };

  if (!isVisible) return null;

  return (
    <div className="fixed bottom-0 left-0 right-0 bg-background border-t border-border shadow-lg z-50 p-4">
      <div className="container mx-auto max-w-6xl">
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
          <div className="flex-1">
            <h3 className="font-semibold text-foreground mb-1">{t('cookies.title')}</h3>
            <p className="text-sm text-muted-foreground">
              {t('cookies.description')}{' '}
              <a href="/privacy" className="text-primary hover:underline">{t('cookies.policy')}</a>.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <button
              onClick={handleAcceptEssential}
              className="px-4 py-2 text-sm font-medium border border-border rounded-lg hover:bg-muted transition-colors"
            >
              {t('cookies.essentialOnly')}
            </button>
            <button
              onClick={handleAcceptAll}
              className="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors"
            >
              {t('cookies.acceptAll')}
            </button>
            <button
              onClick={() => setIsVisible(false)}
              className="p-2 hover:bg-muted rounded-lg transition-colors"
              aria-label={t('close')}
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
