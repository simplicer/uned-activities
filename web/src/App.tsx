import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useState } from 'react';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import { ActivityListPage } from './pages/ActivityListPage';
import { ActivityDetailPage } from './components/ActivityDetail';
import { ProfilePage } from './components/ProfilePage';
import { AuthModal } from './components/AuthModal';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      retry: 1,
    },
  },
});

function AppContent() {
  const { user } = useAuth();
  const [isAuthModalOpen, setIsAuthModalOpen] = useState(false);
  const [isFilterOpen, setIsFilterOpen] = useState(false);

  return (
    <div className="min-h-screen bg-background">
      <header className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 sticky top-0 z-10">
        <div className="container mx-auto px-4 py-4 flex justify-between items-center">
          <a href="/activities" className="text-xl font-bold hover:text-primary transition-colors">
            UNED Activities Finder
          </a>
          <div className="flex items-center gap-4">
            {user ? (
              <a href="/profile" className="text-sm text-foreground hover:text-primary">
                Mi Perfil
              </a>
            ) : (
              <button
                onClick={() => setIsAuthModalOpen(true)}
                className="text-sm px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90"
              >
                Iniciar sesión
              </button>
            )}
          </div>
        </div>
      </header>

      <main className="container mx-auto px-4 py-8">
        <Routes>
          <Route path="/" element={<Navigate to="/activities" replace />} />
          <Route
            path="/activities"
            element={<ActivityListPage isFilterOpen={isFilterOpen} onToggleFilter={() => setIsFilterOpen(!isFilterOpen)} />}
          />
          <Route path="/activities/:id" element={<ActivityDetailPage />} />
          <Route path="/profile" element={<ProfilePage />} />
        </Routes>
      </main>

      <footer className="border-t mt-16">
        <div className="container mx-auto px-4 py-6 text-center text-sm text-muted-foreground">
          <p>&copy; 2025 UNED Activities Finder.</p>
        </div>
      </footer>

      <AuthModal isOpen={isAuthModalOpen} onClose={() => setIsAuthModalOpen(false)} />
    </div>
  );
}

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <AppContent />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  );
}

export default App;
