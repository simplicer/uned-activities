import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route } from 'react-router-dom';
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
import { BookOpen, Menu, X, Heart, Code, FileText, ExternalLink, User, Github, Linkedin, AtSign, Moon, Sun } from 'lucide-react';
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
  const { user } = useAuth();
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);
  const [isFilterOpen, setIsFilterOpen] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const [theme, setTheme] = useState<ThemeMode>(() => getStoredTheme());

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
                <h1 className="text-xl font-bold text-gradient">
                  {t('nav.activities')}
                </h1>
                <p className="text-xs text-muted-foreground">Universidad Nacional de Educación a Distancia</p>
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
                  <span className="text-sm text-muted-foreground">
                    {user.email}
                  </span>
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
                    <span className="px-3 py-2 text-sm text-muted-foreground">
                      {user.email}
                    </span>
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
          <Route path="/activities/:id" element={<ActivityDetailPage />} />
          <Route path="/activities/:id/*" element={<ActivityDetailPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/terms" element={<TermsPage />} />
          <Route path="/privacy" element={<PrivacyPage />} />
        </Routes>
      </main>

      {/* UNED Footer */}
      <footer className="border-t border-primary/10 bg-muted/30 mt-16">
        <div className="container mx-auto px-4 sm:px-6 lg:px-8 py-10">
          <div className="grid gap-10 md:grid-cols-[1.2fr_1fr_1fr]">
            {/* Brand */}
            <div>
              <div className="flex items-center gap-2 mb-4">
                <div className="w-8 h-8 bg-primary rounded-md flex items-center justify-center">
                  <BookOpen className="w-5 h-5 text-white" />
                </div>
                <span className="font-semibold text-foreground">{t('footer.brand')}</span>
              </div>
              <p className="text-sm text-muted-foreground leading-relaxed">
                {t('footer.description')}
              </p>
              <p className="mt-4 text-xs text-muted-foreground">
                {t('footer.disclaimer')}
              </p>
              <div className="mt-4 inline-flex items-center gap-2 text-xs text-muted-foreground">
                <Heart className="w-3.5 h-3.5 text-rose-500" />
                {t('footer.madeWithLove')}
              </div>
            </div>

            {/* Resources */}
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
                  <a href="https://codeberg.org/anvius/uned-activities" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                    <Code className="w-4 h-4" />
                    {t('footer.repo')}
                  </a>
                </li>
                <li>
                  <a href="https://codeberg.org/anvius/uned-activities/src/branch/main/doc" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                    <FileText className="w-4 h-4" />
                    {t('footer.docs')}
                  </a>
                </li>
              </ul>
            </div>

            {/* Creator */}
            <div>
              <h3 className="font-semibold text-foreground mb-4">{t('footer.creator')}</h3>
              <div className="flex items-center gap-2 text-sm text-muted-foreground mb-3">
                <User className="w-4 h-4" />
                <span>{t('footer.creatorName')}</span>
              </div>
              <ul className="space-y-2 text-sm">
                <li>
                  <a href="https://codeberg.org/anvius" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                    <Code className="w-4 h-4" />
                    Codeberg / anvius
                  </a>
                </li>
                <li>
                  <a href="https://github.com/anvius" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                    <Github className="w-4 h-4" />
                    GitHub / anvius
                  </a>
                </li>
                <li>
                  <a href="https://fosstodon.org/@anv" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                    <AtSign className="w-4 h-4" />
                    Mastodon / anv@fosstodon.org
                  </a>
                </li>
                <li>
                  <a href="https://www.linkedin.com/in/villamarin" target="_blank" rel="noopener noreferrer" className="text-muted-foreground hover:text-primary transition-colors inline-flex items-center gap-2">
                    <Linkedin className="w-4 h-4" />
                    LinkedIn / villamarin
                  </a>
                </li>
                <li className="pt-2 text-xs text-muted-foreground inline-flex items-center gap-2">
                  {t('footer.license')}
                  <a href="https://opensource.org/licenses/MIT" target="_blank" rel="noopener noreferrer" className="hover:text-primary transition-colors inline-flex items-center gap-1">
                    MIT
                    <ExternalLink className="w-3 h-3" />
                  </a>
                </li>
              </ul>
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
