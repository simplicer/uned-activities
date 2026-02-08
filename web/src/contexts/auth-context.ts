/**
 * Authentication context types and context instance.
 */

import { createContext } from 'react';
import type { AuthUser } from '@/lib/api/auth';

export interface AuthContextType {
  user: AuthUser | null;
  loading: boolean;
  isAuthenticated: boolean;
  signIn: (email: string) => Promise<void>;
  signInWithPassword: (email: string, password: string) => Promise<void>;
  verifyToken: (token: string) => Promise<void>;
  signOut: () => Promise<void>;
  magicLinkSent: boolean;
  setMagicLinkSent: (sent: boolean) => void;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

