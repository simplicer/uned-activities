/**
 * Authentication provider with magic link authentication.
 */

import { type ReactNode, useEffect, useState } from 'react';
import {
  requestMagicLink,
  verifyMagicLink,
  loginWithPassword,
  storeAuthToken,
  clearAuthToken,
  getStoredUser,
  getStoredAuthToken,
  isAuthenticated as checkIsAuthenticated,
  type AuthUser,
} from '@/lib/api/auth';
import { AuthContext, type AuthContextType } from './auth-context';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [magicLinkSent, setMagicLinkSent] = useState(false);

  // Check for stored auth on mount
  useEffect(() => {
    const storedUser = getStoredUser();
    if (checkIsAuthenticated() && storedUser.id && storedUser.email) {
      setUser({
        id: storedUser.id,
        email: storedUser.email,
        token: getStoredAuthToken() || '',
      });
    }
    setLoading(false);
  }, []);

  const signIn = async (email: string) => {
    try {
      await requestMagicLink(email);
      setMagicLinkSent(true);
    } catch (_error) {
      // Still show success message to prevent email enumeration
      setMagicLinkSent(true);
    }
  };

  const verifyToken = async (token: string) => {
    try {
      const response = await verifyMagicLink(token);
      const authUser: AuthUser = {
        id: response.user.id,
        email: response.user.email,
        token: response.token,
      };
      setUser(authUser);
      storeAuthToken(response.token, response.user.id, response.user.email);
      setMagicLinkSent(false);
    } catch (error) {
      throw new Error(error instanceof Error ? error.message : 'Verification failed');
    }
  };

  const signInWithPassword = async (email: string, password: string) => {
    const response = await loginWithPassword(email, password);
    const authUser: AuthUser = {
      id: response.user.id,
      email: response.user.email,
      token: response.token,
    };
    setUser(authUser);
    storeAuthToken(response.token, response.user.id, response.user.email);
    setMagicLinkSent(false);
  };

  const signOut = async () => {
    setUser(null);
    clearAuthToken();
    setMagicLinkSent(false);
  };

  const value: AuthContextType = {
    user,
    loading,
    isAuthenticated: user !== null,
    signIn,
    signInWithPassword,
    verifyToken,
    signOut,
    magicLinkSent,
    setMagicLinkSent,
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

