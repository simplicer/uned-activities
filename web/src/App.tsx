import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from './components/LanguageSwitcher';

function App() {
  const { t } = useTranslation();

  return (
    <div className="min-h-screen bg-background">
      <header className="border-b">
        <div className="container mx-auto px-4 py-4 flex justify-between items-center">
          <h1 className="text-2xl font-bold">UNED Activities Finder</h1>
          <LanguageSwitcher />
        </div>
      </header>
      <main className="container mx-auto px-4 py-8">
        <div className="max-w-4xl mx-auto">
          <div className="bg-card border rounded-lg p-8">
            <h2 className="text-xl font-semibold mb-4">
              {t('welcome.title', 'Welcome to UNED Activities Finder')}
            </h2>
            <p className="text-muted-foreground mb-4">
              {t('welcome.description', 'Find and track UNED extension courses and activities.')}
            </p>
            <div className="bg-muted rounded p-4">
              <p className="text-sm font-mono">
                API Status: <span className="text-green-600">Connected</span>
              </p>
              <p className="text-sm text-muted-foreground mt-2">
                {t('common.comingSoon', 'More features coming soon...')}
              </p>
            </div>
          </div>
        </div>
      </main>
      <footer className="border-t mt-16">
        <div className="container mx-auto px-4 py-6 text-center text-sm text-muted-foreground">
          <p>&copy; 2025 UNED Activities Finder. Phase 0 - Foundations.</p>
        </div>
      </footer>
    </div>
  );
}

export default App;
