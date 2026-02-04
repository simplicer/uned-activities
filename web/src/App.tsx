import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { ErrorBoundary } from './components/ErrorBoundary';
import { ActivityListPage } from './pages/ActivityListPage';
import { ActivityDetailPage } from './components/ActivityDetail';
import { ProfilePage } from './components/ProfilePage';
import { AuthModal } from './components/AuthModal';
import { LanguageSwitcher } from './components/LanguageSwitcher';
import { CookieConsent } from './components/CookieConsent';
import { TermsPage } from './pages/TermsPage';
import { PrivacyPage } from './pages/PrivacyPage';
import { ContactPage } from './pages/ContactPage';
import { BookOpen, Menu, X, Heart, Code, FileText, ExternalLink, Moon, Sun, Scale } from 'lucide-react';
import { applyTheme, getStoredTheme, getSystemTheme, setStoredTheme, type ThemeMode } from '@/lib/theme';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});

function AppContent() {
  const { t } = useTranslation();
  const { user, signOut } = useAuth();
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [theme, setTheme] = useState<ThemeMode>(() => getStoredTheme());
  const location = useLocation();

  const resolvedTheme = theme === 'system' ? getSystemTheme() : theme;

  useEffect(() => {
    applyTheme(theme);
    setStoredTheme(theme);
  }, [theme]);

  useEffect(() => {
    if (theme !== 'system') return;
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = () => applyTheme('system');
    media.addEventListener('change', handler);
    return () => media.removeEventListener('change', handler);
  }, [theme]);

  useEffect(() => {
    const params = new URLSearchParams(location.search);
    if (params.get('token')) {
      setIsAuthModalOpen(true);
    }
  }, [location.search]);

  const toggleTheme = () => {
    setTheme(resolvedTheme === 'dark' ? 'light' : 'dark');
  };

  return (
    <div className="min-h-screen bg-background">
      {/* UNED Header */}
      <header className="border-b border-primary/10 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 sticky top-0 z-50 shadow-sm">
        <div className="container mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            {/* Logo and Title - links to homepage (activities) */}
            <a
              href="/"
              className="flex items-center gap-3 group"
            >
              <div className="w-10 h-10 bg-primary rounded-lg flex items-center justify-center shadow-lg group-hover:shadow-xl transition-shadow">
                <BookOpen className="w-6 h-6 text-white" />
              </div>
              <div className="hidden sm:block">
                <h1 className="text-xl font-bold text-gradient">LEXEMAS</h1>
                <p className="text-xs text-muted-foreground">{t('footer.description')}</p>
              </div>
            </a>

            {/* Desktop Navigation */}
            <nav className="hidden md:flex items-center gap-6">
              {user ? (
                <>
                  <a
                    href="/profile"
                    className="text-sm font-medium text-foreground hover:text-primary transition-colors"
                  >
                    {t('nav.profile')}
                  </a>
                  <button
                    onClick={() => signOut()}
                    className="btn-secondary text-sm"
                  >
                    {t('signOut')}
                  </button>
                </>
              ) : (
                <button
                  onClick={() => setIsAuthModalOpen(true)}
                  className="btn-primary text-sm"
                >
                  {t('auth.signIn')}
                </button>
              )}
              <button
                onClick={toggleTheme}
                className="p-2 rounded-lg hover:bg-muted transition-colors"
                aria-label={t('theme.toggle')}
                title={t('theme.toggle')}
              >
                {resolvedTheme === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
              </button>
              <LanguageSwitcher />
            </nav>

            {/* Mobile Menu Button */}
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="md:hidden p-2 rounded-lg hover:bg-muted transition-colors"
              aria-label={t('close')}
            >
              {isMobileMenuOpen ? (
                <X className="w-6 h-6" />
              ) : (
                <Menu className="w-6 h-6" />
              )}
            </button>
          </div>

          {/* Mobile Navigation */}
          {isMobileMenuOpen && (
            <nav className="md:hidden py-4 border-t border-border">
              <div className="flex flex-col gap-3">
                {user ? (
                  <>
                    <a
                      href="/profile"
                      className="px-3 py-2 rounded-lg hover:bg-muted transition-colors text-sm font-medium"
                      onClick={() => setIsMobileMenuOpen(false)}
                    >
                      {t('nav.profile')}
                    </a>
                    <button
                      onClick={() => {
                        signOut();
                        setIsMobileMenuOpen(false);
                      }}
                      className="btn-secondary text-sm text-left"
                    >
                      {t('signOut')}
                    </button>
                  </>
                ) : (
                  <button
                    onClick={() => {
                      setIsAuthModalOpen(true);
                      setIsMobileMenuOpen(false);
                    }}
                    className="btn-primary text-sm text-left"
                  >
                    {t('auth.signIn')}
                  </button>
                )}
                <div className="px-3 py-2">
                  <button
                    onClick={toggleTheme}
                    className="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-muted transition-colors text-sm font-medium"
                  >
                    {resolvedTheme === 'dark' ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    {t('theme.toggle')}
                  </button>
                </div>
                <div className="px-3 py-2">
                  <LanguageSwitcher />
                </div>
              </div>
            </nav>
          )}
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
        <Routes>
          <Route
            path="/"
            element={<ActivityListPage isFilterOpen={isFilterOpen} onToggleFilter={() => setIsFilterOpen(!isFilterOpen)} />}
          />
          <Route
            path="/auth/verify"
            element={<ActivityListPage isFilterOpen={isFilterOpen} onToggleFilter={() => setIsFilterOpen(!isFilterOpen)} />}
          />
          <Route path="/activities/:id" element={<ActivityDetailPage />} />
          <Route path="/activities/:id/*" element={<ActivityDetailPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/terms" element={<TermsPage />} />
          <Route path="/privacy" element={<PrivacyPage />} />
          <Route path="/contact" element={<ContactPage />} />
        </Routes>
      </main>

      {/* UNED Footer */}
      <footer className="border-t border-primary/10 bg-muted/30 mt-16">
        <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-10">
          <div className="grid gap-10 md:grid-cols-[1.2fr_1.4fr]">
            {/* Brand */}
            <div>
              <div className="mt-4 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span className="inline-flex items-center gap-2">
                  <Heart className="w-3.5 h-3.5 text-rose-500" />
                  <span>
                    {t('footer.madeWithLove')},{' '}
                    <span>por</span>{' '}
                    <span className="font-simplicer text-[#c02626] tracking-wide relative -top-px">simplicer</span>
                  </span>
                </span>
              </div>
              <p className="mt-3 text-xs text-muted-foreground">
                {t('footer.disclaimer')}
              </p>
              <div className="mt-3 text-xs text-muted-foreground">
                <a href="https://codeberg.org/simplicer/uned-activities/src/branch/main/LICENSE" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-1">
                  <Scale className="w-3.5 h-3.5" />
                  {t('footer.licenseMit')}
                </a>
              </div>
            </div>

            {/* Resources + Legal */}
            <div className="md:justify-self-end w-full">
              <div className="grid gap-8 sm:grid-cols-2 sm:justify-items-end">
                <div>
                  <h3 className="font-semibold text-foreground mb-4">{t('footer.resources')}</h3>
                  <ul className="space-y-2 text-sm">
                    <li>
                      <a href="https://extension.uned.es" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                        {t('footer.unedExtension')}
                        <ExternalLink className="w-3.5 h-3.5" />
                      </a>
                    </li>
                    <li>
                      <a href="https://codeberg.org/simplicer/uned-activities" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                        <Code className="w-4 h-4" />
                        {t('footer.repo')}
                      </a>
                    </li>
                    <li>
                      <a href="https://codeberg.org/simplicer/uned-activities/src/branch/main/doc" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                        <FileText className="w-4 h-4" />
                        {t('footer.docs')}
                      </a>
                    </li>
                  </ul>
                </div>
                <div>
                  <h3 className="font-semibold text-foreground mb-4">{t('footer.legal')}</h3>
                  <ul className="space-y-2 text-sm">
                    <li>
                      <a href="/terms" className="text-muted-foreground hover:text-primary transition-colors">
                        {t('termsOfUse')}
                      </a>
                    </li>
                    <li>
                      <a href="/privacy" className="text-muted-foreground hover:text-primary transition-colors">
                        {t('privacyPolicy')}
                      </a>
                    </li>
                    <li>
                      <a href="/contact" className="text-muted-foreground hover:text-primary transition-colors">
                        {t('footer.contact')}
                      </a>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </footer>

      <AuthModal isOpen={isAuthModalOpen} onClose={() => setIsAuthModalOpen(false)} />
      <CookieConsent />
    </div>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <ErrorBoundary>
            <AppContent />
          </ErrorBoundary>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;
