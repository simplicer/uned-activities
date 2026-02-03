import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

// Import translations
import en from './locales/en/common.json';
import es from './locales/es/common.json';
import ca from './locales/ca/common.json';
import val from './locales/val/common.json';
import eu from './locales/eu/common.json';
import gl from './locales/gl/common.json';

const resources = {
  en: { common: en },
  es: { common: es },
  ca: { common: ca },
  val: { common: val },
  eu: { common: eu },
  gl: { common: gl },
};

// Get saved language or default to browser language
// Wrap localStorage access in try-catch to avoid SSR issues
const getInitialLanguage = (): string => {
  try {
    const savedLanguage = localStorage.getItem('language');
    if (savedLanguage && ['en', 'es', 'ca', 'val', 'eu', 'gl'].includes(savedLanguage)) {
      return savedLanguage;
    }
  } catch {
    // localStorage not available (SSR or private browsing)
  }

  const browserLanguage = navigator.language.split('-')[0];
  return ['en', 'es', 'ca', 'val', 'eu', 'gl'].includes(browserLanguage)
    ? browserLanguage
    : 'en';
};

i18n
  .use(initReactI18next)
  .init({
    resources,
    lng: getInitialLanguage(),
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: ['common'],
    interpolation: {
      escapeValue: false,
    },
  });

export default i18n;
