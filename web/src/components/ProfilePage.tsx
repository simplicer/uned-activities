/**
 * User profile page component.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/contexts/AuthContext';
import {
  getProfile,
  updateProfile,
  getSavedSearches,
  createSavedSearch,
  deleteSavedSearch,
  getFavorites,
  removeFavorite,
  updateFavorite,
  setPassword,
  deleteAccount,
  getNotifications,
  markNotificationRead,
} from '@/lib/api/profile';
import { Loader2, Trash2, Plus, Bell, BellOff, Star, Eye, EyeOff, Check } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type { SavedSearch, FavoriteActivity } from '@/lib/api/profile';

export function ProfilePage() {
  const { user, signOut } = useAuth();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [activeSection, setActiveSection] = useState<'profile' | 'favorites' | 'searches' | 'notifications'>('profile');
  const [showSaveForm, setShowSaveForm] = useState(false);
  const [newSearchName, setNewSearchName] = useState('');
  const [passwordInput, setPasswordInput] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [passwordMessage, setPasswordMessage] = useState('');
  const [passwordMessageType, setPasswordMessageType] = useState<'success' | 'error' | ''>('');
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirm, setShowPasswordConfirm] = useState(false);
  const [favoriteEdits, setFavoriteEdits] = useState<Record<string, { enrolled?: boolean; rating?: string; notifyOnChange?: boolean }>>({});
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [profileForm, setProfileForm] = useState({
    fullName: '',
    municipality: '',
    province: '',
    country: '',
    defaultLanguage: '',
    studyLevel: '',
    studyProgram: '',
  });

  const toBool = (value: unknown, fallback = false): boolean => {
    if (value === undefined || value === null) {
      return fallback;
    }
    if (value === true || value === false) {
      return value;
    }
    if (value === 't' || value === 'true' || value === 1 || value === '1') {
      return true;
    }
    if (value === 'f' || value === 'false' || value === 0 || value === '0') {
      return false;
    }
    return Boolean(value);
  };

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

  // Get favorites
  const { data: favorites, isLoading: favoritesLoading, isError: favoritesError, error: favoritesErrorData } = useQuery({
    queryKey: ['favorites'],
    queryFn: () => getFavorites(),
    enabled: !!user,
  });

  // Get notifications
  const { data: notifications, isLoading: notificationsLoading, isError: notificationsError, error: notificationsErrorData } = useQuery({
    queryKey: ['notifications'],
    queryFn: () => getNotifications(),
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

  const removeFavoriteMutation = useMutation({
    mutationFn: removeFavorite,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
      queryClient.invalidateQueries({ queryKey: ['favorite-ids'] });
    },
  });

  const updateFavoriteMutation = useMutation({
    mutationFn: ({ activityId, data }: { activityId: string; data: { enrolled?: boolean; rating?: number | null; notifyOnChange?: boolean } }) =>
      updateFavorite(activityId, data),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['favorites'] });
    },
  });

  const setPasswordMutation = useMutation({
    mutationFn: setPassword,
    onSuccess: () => {
      setPasswordMessage('Contraseña guardada correctamente.');
      setPasswordMessageType('success');
      setPasswordInput('');
      setPasswordConfirm('');
    },
    onError: (error: unknown) => {
      setPasswordMessage(error instanceof Error ? error.message : 'No se pudo guardar la contraseña.');
      setPasswordMessageType('error');
    },
  });

  const markNotificationMutation = useMutation({
    mutationFn: markNotificationRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const deleteAccountMutation = useMutation({
    mutationFn: deleteAccount,
    onSuccess: async () => {
      await signOut();
      navigate('/');
    },
  });

  useEffect(() => {
    if (!profile) return;
    const prefs = profile.preferences || {};
    setProfileForm({
      fullName: profile.fullName || '',
      municipality: (prefs.municipality as string) || '',
      province: (prefs.province as string) || '',
      country: (prefs.country as string) || '',
      defaultLanguage: (prefs.defaultLanguage as string) || '',
      studyLevel: (prefs.studyLevel as string) || '',
      studyProgram: (prefs.studyProgram as string) || '',
    });
  }, [profile]);

  if (!user) {
    return (
      <div className="max-w-4xl mx-auto text-center py-12">
        <p className="text-muted-foreground">Debes iniciar sesión para ver tu perfil</p>
        <button
          onClick={() => navigate('/')}
          className="mt-4 px-4 py-2 bg-primary text-primary-foreground rounded-md"
        >
          Volver al catálogo
        </button>
      </div>
    );
  }

  if (profileLoading || searchesLoading || favoritesLoading || notificationsLoading) {
    return (
      <div className="flex items-center justify-center py-12">
        <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Mi cuenta</h1>
        <button
          onClick={() => navigate('/')}
          className="px-4 py-2 border rounded-md hover:bg-muted text-sm"
        >
          Volver al catálogo
        </button>
      </div>

      <div className="flex flex-wrap gap-2 mb-6">
        {[
          { id: 'profile', label: 'Perfil' },
          { id: 'favorites', label: 'Favoritos' },
          { id: 'searches', label: 'Búsquedas' },
          { id: 'notifications', label: 'Notificaciones' },
        ].map((item) => (
          <button
            key={item.id}
            onClick={() => setActiveSection(item.id as typeof activeSection)}
            className={`px-4 py-2 rounded-md text-sm border transition-colors ${
              activeSection === item.id
                ? 'bg-primary text-primary-foreground border-primary'
                : 'bg-background hover:bg-muted'
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {activeSection === 'profile' && (
        <div className="bg-card border rounded-lg p-6 mb-6">
          <h2 className="text-lg font-semibold mb-4">Información personal</h2>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">Email</label>
              <p className="text-muted-foreground">{user.email}</p>
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Nombre completo</label>
              <input
                type="text"
                value={profileForm.fullName}
                onChange={(e) => setProfileForm((prev) => ({ ...prev, fullName: e.target.value }))}
                className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                placeholder="Nombre y apellidos"
              />
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-medium mb-1">Municipio</label>
                <input
                  type="text"
                  value={profileForm.municipality}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, municipality: e.target.value }))}
                  className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                  placeholder="Municipio"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Provincia</label>
                <input
                  type="text"
                  value={profileForm.province}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, province: e.target.value }))}
                  className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                  placeholder="Provincia"
                />
              </div>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-medium mb-1">País</label>
                <input
                  type="text"
                  value={profileForm.country}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, country: e.target.value }))}
                  className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                  placeholder="País"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Idioma por defecto</label>
                <input
                  type="text"
                  value={profileForm.defaultLanguage}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, defaultLanguage: e.target.value }))}
                  className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                  placeholder="Idioma"
                />
              </div>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="block text-sm font-medium mb-1">Nivel de estudios</label>
                <select
                  value={profileForm.studyLevel}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, studyLevel: e.target.value }))}
                  className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                >
                  <option value="">Seleccionar</option>
                  <option value="grado">Grado</option>
                  <option value="master">Máster</option>
                  <option value="doctorado">Doctorado</option>
                  <option value="otro">Otro</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Qué estudias</label>
                <input
                  type="text"
                  value={profileForm.studyProgram}
                  onChange={(e) => setProfileForm((prev) => ({ ...prev, studyProgram: e.target.value }))}
                  className="flex-1 px-3 py-2 border rounded-md bg-background w-full"
                  placeholder="Grado, máster o doctorado"
                />
              </div>
            </div>
            <div className="flex justify-end">
              <button
                onClick={() => {
                  updateProfileMutation.mutate({
                    fullName: profileForm.fullName,
                    preferences: {
                      municipality: profileForm.municipality,
                      province: profileForm.province,
                      country: profileForm.country,
                      defaultLanguage: profileForm.defaultLanguage,
                      studyLevel: profileForm.studyLevel,
                      studyProgram: profileForm.studyProgram,
                    },
                  });
                }}
                className="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm disabled:opacity-50"
                disabled={updateProfileMutation.isPending}
              >
                {updateProfileMutation.isPending ? 'Guardando...' : 'Guardar cambios'}
              </button>
            </div>
          </div>
        </div>
      )}

      {activeSection === 'favorites' && (
        <div className="bg-card border rounded-lg p-6 mb-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-semibold">Favoritos</h2>
          </div>
          {favoritesError ? (
            <div className="text-center py-8 text-muted-foreground">
              <p>No se pudieron cargar tus favoritos.</p>
              {favoritesErrorData instanceof Error && (
                <p className="text-xs mt-1">{favoritesErrorData.message}</p>
              )}
            </div>
          ) : favorites && favorites.length > 0 ? (
            <div className="space-y-3">
              {favorites.map((favorite: FavoriteActivity) => {
                const edit = favoriteEdits[favorite.activity_id] || {};
                const enrolled = edit.enrolled ?? toBool(favorite.enrolled, false);
                const ratingValue = edit.rating ?? (favorite.user_rating !== null && favorite.user_rating !== undefined ? String(favorite.user_rating) : '');
                const notifyOnChange = edit.notifyOnChange ?? toBool(favorite.notify_on_change, true);
                const ratingNumber = ratingValue === '' ? 0 : Number(ratingValue);

                return (
                  <div key={favorite.activity_id} className="p-3 bg-muted/30 rounded-lg">
                    <div className="flex items-center justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <Star className="w-4 h-4 text-amber-500" />
                          <a href={`/activities/${favorite.activity_id}`} className="font-medium hover:underline">
                            {favorite.title || 'Actividad sin título'}
                          </a>
                        </div>
                        <p className="text-sm text-muted-foreground">
                          {favorite.center || 'Centro no disponible'}
                        </p>
                      </div>
                      <button
                        onClick={() => {
                          if (confirm('¿Eliminar esta actividad de favoritos?')) {
                            removeFavoriteMutation.mutate(favorite.activity_id);
                          }
                        }}
                        className="p-2 text-muted-foreground hover:text-destructive"
                        disabled={removeFavoriteMutation.isPending}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>

                    <div className="mt-3 grid gap-3 md:grid-cols-3">
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={enrolled}
                          onChange={(e) => setFavoriteEdits((prev) => ({
                            ...prev,
                            [favorite.activity_id]: { ...prev[favorite.activity_id], enrolled: e.target.checked },
                          }))}
                        />
                        Matriculada
                      </label>
                      <div className="flex items-center gap-2 text-sm">
                        <span className="text-muted-foreground">Nota</span>
                        <div className="flex items-center gap-1">
                          {[1, 2, 3, 4, 5].map((value) => (
                            <button
                              key={value}
                              type="button"
                              onClick={() => setFavoriteEdits((prev) => ({
                                ...prev,
                                [favorite.activity_id]: { ...prev[favorite.activity_id], rating: String(value) },
                              }))}
                              className={`p-1 ${ratingNumber >= value ? 'text-amber-500' : 'text-muted-foreground'}`}
                              aria-label={`Puntuar ${value}`}
                            >
                              <Star className={`w-4 h-4 ${ratingNumber >= value ? 'fill-current' : ''}`} />
                            </button>
                          ))}
                          {ratingNumber > 0 && (
                            <button
                              type="button"
                              onClick={() => setFavoriteEdits((prev) => ({
                                ...prev,
                                [favorite.activity_id]: { ...prev[favorite.activity_id], rating: '' },
                              }))}
                              className="ml-2 text-xs text-muted-foreground hover:text-foreground"
                            >
                              Borrar
                            </button>
                          )}
                        </div>
                      </div>
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={notifyOnChange}
                          onChange={(e) => setFavoriteEdits((prev) => ({
                            ...prev,
                            [favorite.activity_id]: { ...prev[favorite.activity_id], notifyOnChange: e.target.checked },
                          }))}
                        />
                        Avisarme si cambia
                      </label>
                    </div>

                    <div className="flex justify-end mt-3">
                      <button
                        onClick={() => {
                          if (ratingValue !== '' && isNaN(Number(ratingValue))) {
                            return;
                          }
                          updateFavoriteMutation.mutate({
                            activityId: favorite.activity_id,
                            data: {
                              enrolled,
                              rating: ratingValue === '' ? null : Number(ratingValue),
                              notifyOnChange,
                            },
                          });
                        }}
                        className="px-3 py-2 bg-primary text-primary-foreground rounded-md text-sm"
                        disabled={updateFavoriteMutation.isPending}
                      >
                        {updateFavoriteMutation.isPending ? 'Guardando...' : 'Guardar'}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              <p>No tienes actividades favoritas todavía.</p>
            </div>
          )}
        </div>
      )}

      {activeSection === 'searches' && (
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
                    let currentFilters = {};
                    try {
                      const raw = localStorage.getItem('activities_filters');
                      if (raw) {
                        currentFilters = JSON.parse(raw);
                      }
                    } catch {
                      currentFilters = {};
                    }
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

        {searches && searches.length > 0 ? (
          <div className="space-y-2">
            {searches.map((search: SavedSearch) => (
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
      )}

      {activeSection === 'notifications' && (
        <div className="bg-card border rounded-lg p-6">
          <div className="flex justify-between items-center mb-4">
            <h2 className="text-lg font-semibold">Notificaciones</h2>
          </div>
          <div className="mb-6">
            <h3 className="text-sm font-semibold text-muted-foreground mb-2">Avisos activos</h3>
            {favoritesError ? (
              <p className="text-sm text-muted-foreground">No se pudieron cargar los avisos activos.</p>
            ) : favorites && favorites.filter((fav) => toBool(fav.notify_on_change, true)).length > 0 ? (
              <div className="space-y-2">
                {favorites
                  .filter((fav) => toBool(fav.notify_on_change, true))
                  .map((fav) => (
                    <div key={`active-${fav.activity_id}`} className="flex items-center justify-between p-3 bg-muted/30 rounded-lg">
                      <div>
                        <p className="font-medium">{fav.title || 'Actividad sin título'}</p>
                        <p className="text-xs text-muted-foreground">{fav.center || 'Centro no disponible'}</p>
                      </div>
                      <a href={`/activities/${fav.activity_id}`} className="text-sm text-primary hover:underline">
                        Ver actividad
                      </a>
                    </div>
                  ))}
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">No tienes avisos activos.</p>
            )}
          </div>
          {notificationsError ? (
            <div className="text-center py-8 text-muted-foreground">
              <p>No se pudieron cargar las notificaciones.</p>
              {notificationsErrorData instanceof Error && (
                <p className="text-xs mt-1">{notificationsErrorData.message}</p>
              )}
            </div>
          ) : notifications && notifications.length > 0 ? (
            <div className="space-y-2">
              {notifications.map((notification) => (
                <div key={notification.id} className="p-3 bg-muted/30 rounded-lg">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-medium">{notification.title}</p>
                      <p className="text-sm text-muted-foreground">{notification.message}</p>
                      <p className="text-xs text-muted-foreground mt-1">{notification.createdAt}</p>
                    </div>
                    {!notification.isRead && (
                      <button
                        onClick={() => markNotificationMutation.mutate(notification.id)}
                        className="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                      >
                        <Check className="w-3 h-3" />
                        Marcar leída
                      </button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-8 text-muted-foreground">
              <p>No tienes notificaciones todavía.</p>
            </div>
          )}
        </div>
      )}

      {activeSection === 'profile' && (
        <>
          <div className="bg-card border rounded-lg p-6 mt-6">
            <h2 className="text-lg font-semibold mb-4">Contraseña</h2>
            <p className="text-sm text-muted-foreground mb-4">
              Puedes establecer una contraseña para acceder sin enlace mágico. Debe tener al menos 12 caracteres y 6 distintos.
              Ejemplo: “En un lugar de la Mancha”.
            </p>
            <div className="flex flex-col gap-3">
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={passwordInput}
                  onChange={(e) => setPasswordInput(e.target.value)}
                  placeholder="Escribe una contraseña segura"
                  className="w-full px-3 py-2 border rounded-md bg-background pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((prev) => !prev)}
                  className="absolute right-2 top-2.5 text-muted-foreground hover:text-foreground"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              <div className="relative">
                <input
                  type={showPasswordConfirm ? 'text' : 'password'}
                  value={passwordConfirm}
                  onChange={(e) => setPasswordConfirm(e.target.value)}
                  placeholder="Repite la contraseña"
                  className="w-full px-3 py-2 border rounded-md bg-background pr-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPasswordConfirm((prev) => !prev)}
                  className="absolute right-2 top-2.5 text-muted-foreground hover:text-foreground"
                >
                  {showPasswordConfirm ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
              {passwordMessage && (
                <p className={`text-sm ${passwordMessageType === 'error' ? 'text-destructive' : 'text-emerald-600'}`}>
                  {passwordMessage}
                </p>
              )}
              <div className="flex justify-end">
                <button
                  onClick={() => {
                    const uniqueChars = new Set(passwordInput).size;
                    if (passwordInput.length < 12 || uniqueChars < 6) {
                      setPasswordMessage('La contraseña no cumple los requisitos.');
                      setPasswordMessageType('error');
                      return;
                    }
                    if (passwordInput !== passwordConfirm) {
                      setPasswordMessage('Las contraseñas no coinciden.');
                      setPasswordMessageType('error');
                      return;
                    }
                    setPasswordMessage('');
                    setPasswordMessageType('');
                    setPasswordMutation.mutate(passwordInput);
                  }}
                  disabled={setPasswordMutation.isPending}
                  className="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm disabled:opacity-50"
                >
                  {setPasswordMutation.isPending ? 'Guardando...' : 'Guardar contraseña'}
                </button>
              </div>
            </div>
          </div>

          <div className="bg-card border rounded-lg p-6 mt-6">
            <h2 className="text-lg font-semibold mb-4">Eliminar cuenta</h2>
            <p className="text-sm text-muted-foreground mb-4">
              Esta acción eliminará definitivamente tu cuenta y todos los datos asociados. No se podrán recuperar.
            </p>
            <div className="flex justify-end">
              <button
                onClick={() => setShowDeleteModal(true)}
                className="px-4 py-2 border border-destructive text-destructive rounded-md text-sm hover:bg-destructive/10"
              >
                Eliminar cuenta
              </button>
            </div>
          </div>

          {showDeleteModal && (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
              <div className="bg-card rounded-xl border border-border w-full max-w-md p-6 space-y-4">
                <h3 className="text-lg font-semibold">Eliminar cuenta</h3>
                <p className="text-sm text-muted-foreground">
                  Esta acción es irreversible. Perderás todos los datos almacenados y no se podrán recuperar.
                </p>
                <div className="flex justify-end gap-2">
                  <button
                    onClick={() => setShowDeleteModal(false)}
                    className="px-4 py-2 border rounded-md text-sm"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={() => deleteAccountMutation.mutate()}
                    className="px-4 py-2 bg-destructive text-destructive-foreground rounded-md text-sm"
                    disabled={deleteAccountMutation.isPending}
                  >
                    {deleteAccountMutation.isPending ? 'Eliminando...' : 'Confirmar eliminación'}
                  </button>
                </div>
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}
