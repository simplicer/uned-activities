/**
 * User profile page component.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/contexts/AuthContext';
import { getProfile, updateProfile, getSavedSearches, createSavedSearch, deleteSavedSearch } from '@/lib/api/profile';
import { Loader2, Save, Trash2, Plus, Bell, BellOff } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type { SavedSearch } from '@/lib/api/profile';

export function ProfilePage() {
  const { user, signOut } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [showSaveForm, setShowSaveForm] = useState(false);
  const [newSearchName, setNewSearchName] = useState('');

  // Get user profile
  const { data: profile, isLoading: profileLoading } = useQuery({
    queryKey: ['profile'],
    queryFn: () => getProfile(),
    enabled: !!user,
  });

  // Get saved searches
  const { data: searches, isLoading: searchesLoading } = useQuery({
    queryKey: ['saved-searches'],
    queryFn: () => getSavedSearches(),
    enabled: !!user,
  });

  // Update profile mutation
  const updateProfileMutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['profile'] });
    },
  });

  // Create saved search mutation
  const createSearchMutation = useMutation({
    mutationFn: createSavedSearch,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saved-searches'] });
      setShowSaveForm(false);
      setNewSearchName('');
    },
  });

  // Delete saved search mutation
  const deleteSearchMutation = useMutation({
    mutationFn: deleteSavedSearch,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['saved-searches'] });
    },
  });

  const handleSignOut = async () => {
    await signOut();
    navigate('/activities');
  };

  if (!user) {
    return (
      <div className="max-w-4xl mx-auto text-center py-12">
        <p className="text-muted-foreground">Debes iniciar sesión para ver tu perfil</p>
        <button
          onClick={() => navigate('/activities')}
          className="mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-md"
        >
          Volver al catálogo
        </button>
      </div>
    );
  }

  if (profileLoading || searchesLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Mi Perfil</h1>
        <button
          onClick={handleSignOut}
          className="px-4 py-2 border rounded-md hover:bg-muted text-sm"
        >
          Cerrar sesión
        </button>
      </div>

      {/* Profile Info */}
      <div className="bg-card border rounded-lg p-6 mb-6">
        <h2 className="text-lg font-semibold mb-4">Información personal</h2>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Email</label>
            <p className="text-muted-foreground">{user.email}</p>
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Nombre completo</label>
            <div className="flex gap-2">
              <input
                type="text"
                defaultValue={profile?.data.fullName || ''}
                onBlur={(e) => {
                  if (e.target.value !== profile?.data.fullName) {
                    updateProfileMutation.mutate({ fullName: e.target.value });
                  }
                }}
                className="flex-1 px-3 py-2 border rounded-md bg-background"
                placeholder="Tu nombre"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Saved Searches */}
      <div className="bg-card border rounded-lg p-6">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold">Búsquedas guardadas</h2>
          <button
            onClick={() => setShowSaveForm(!showSaveForm)}
            className="flex items-center gap-2 px-3 py-2 bg-primary text-primary-foreground rounded-md text-sm"
          >
            <Plus className="w-4 h-4" />
            Nueva búsqueda
          </button>
        </div>

        {showSaveForm && (
          <div className="bg-muted/50 rounded-lg p-4 mb-4">
            <input
              type="text"
              value={newSearchName}
              onChange={(e) => setNewSearchName(e.target.value)}
              placeholder="Nombre de la búsqueda"
              className="w-full px-3 py-2 border rounded-md bg-background mb-2"
            />
            <p className="text-sm text-muted-foreground mb-3">
              Se guardará con los filtros actuales
            </p>
            <div className="flex gap-2">
              <button
                onClick={() => {
                  if (newSearchName.trim()) {
                    // Get current filters from localStorage or URL params
                    const currentFilters = {}; // TODO: get from state
                    createSearchMutation.mutate({
                      name: newSearchName,
                      filters: currentFilters,
                      notifyOnNew: false,
                    });
                  }
                }}
                disabled={!newSearchName.trim() || createSearchMutation.isPending}
                className="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm disabled:opacity-50"
              >
                {createSearchMutation.isPending ? 'Guardando...' : 'Guardar'}
              </button>
              <button
                onClick={() => {
                  setShowSaveForm(false);
                  setNewSearchName('');
                }}
                className="px-4 py-2 border rounded-md text-sm"
              >
                Cancelar
              </button>
            </div>
          </div>
        )}

        {searches?.data && searches.data.length > 0 ? (
          <div className="space-y-2">
            {searches.data.map((search: SavedSearch) => (
              <div key={search.id} className="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <h3 className="font-medium">{search.name}</h3>
                    {search.notifyOnNew ? (
                      <Bell className="w-4 h-4 text-green-500" />
                    ) : (
                      <BellOff className="w-4 h-4 text-gray-400" />
                    )}
                  </div>
                  <p className="text-sm text-muted-foreground">
                    {Object.keys(search.filters).length} filtros
                  </p>
                </div>
                <button
                  onClick={() => {
                    if (confirm('¿Eliminar esta búsqueda guardada?')) {
                      deleteSearchMutation.mutate(search.id);
                    }
                  }}
                  className="p-2 text-muted-foreground hover:text-destructive"
                  disabled={deleteSearchMutation.isPending}
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-8 text-muted-foreground">
            <p>No tienes búsquedas guardadas</p>
            <p className="text-sm mt-1">
              Guarda tus filtros favoritos para recibir notificaciones de nuevas actividades
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
