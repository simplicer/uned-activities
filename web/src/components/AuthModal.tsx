/**
 * Authentication modal with magic link flow.
 */

import { useCallback, useEffect, useState } from 'react';
import { X, Mail, CheckCircle, Clock } from 'lucide-react';
import { useAuth } from '@/contexts/useAuth';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function AuthModal({ isOpen, onClose }: AuthModalProps) {
  const { t } = useTranslation();
  const { signIn, signInWithPassword, verifyToken, magicLinkSent, setMagicLinkSent } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [authMode, setAuthMode] = useState<'magic' | 'password'>('magic');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [pendingToken, setPendingToken] = useState<string | null>(null);
  const navigate = useNavigate();

  // A token in the URL is only a proposal, never a sign-in command: the user
  // must confirm it. Otherwise anyone could force a victim's browser to adopt
  // an attacker-owned session by sending a crafted /?token=<attacker-token> link.
  const clearTokenFromUrl = useCallback(() => {
    const url = new URL(window.location.href);
    url.searchParams.delete('token');
    window.history.replaceState({}, '', url.toString());
  }, []);

  const handleVerifyToken = useCallback(async (token: string) => {
    setIsSubmitting(true);
    setError('');

    try {
      await verifyToken(token);
      onClose();
      // Clean URL
      navigate('/', { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : t('auth.invalidLink'));
    } finally {
      setIsSubmitting(false);
    }
  }, [navigate, onClose, t, verifyToken]);

  // Detect a token in the URL on mount and stage it for confirmation
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (token) {
      setPendingToken(token);
    }
  }, []);

  const handleConfirmToken = useCallback(async () => {
    if (pendingToken === null) return;
    await handleVerifyToken(pendingToken);
    setPendingToken(null);
  }, [handleVerifyToken, pendingToken]);

  const handleCancelToken = useCallback(() => {
    clearTokenFromUrl();
    setPendingToken(null);
    onClose();
  }, [clearTokenFromUrl, onClose]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!email || !email.includes('@')) {
      setError(t('auth.invalidEmail'));
      return;
    }

    setIsSubmitting(true);

    try {
      if (authMode === 'password') {
        if (!password) {
          setError(t('auth.passwordRequired'));
          setIsSubmitting(false);
          return;
        }
        await signInWithPassword(email, password);
        onClose();
        navigate('/', { replace: true });
      } else {
        await signIn(email);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : t('auth.invalidCredentials'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = useCallback(() => {
    clearTokenFromUrl();
    setPendingToken(null);
    onClose();
    setMagicLinkSent(false);
    setEmail('');
    setPassword('');
    setError('');
    setAuthMode('magic');
  }, [onClose, setMagicLinkSent]);

  useEffect(() => {
    if (!isOpen) return;
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        handleClose();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [handleClose, isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div className="bg-card rounded-2xl shadow-2xl w-full max-w-md relative">
        {/* Close button */}
        <button
          onClick={handleClose}
          className="absolute top-4 right-4 p-2 hover:bg-muted rounded-lg transition-colors"
          aria-label={t('close')}
        >
          <X className="w-5 h-5" />
        </button>

        <div className="p-8">
          {/* Magic-link confirmation step (never auto-verify a URL token) */}
          {pendingToken !== null ? (
            <div className="text-center">
              <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                <Mail className="w-8 h-8 text-primary" />
              </div>
              <h2 className="text-2xl font-bold text-foreground">
                {t('auth.confirmLinkTitle', 'Sign in with this emailed link?')}
              </h2>
              <p className="text-muted-foreground mt-2 mb-8">
                {t('auth.confirmLinkBody', 'Only confirm if you requested this link or trust the person who sent it.')}
              </p>
              <button
                onClick={handleConfirmToken}
                disabled={isSubmitting}
                className="w-full py-3 bg-primary text-primary-foreground rounded-xl font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50"
              >
                {isSubmitting ? t('auth.signingIn', 'Signing in…') : t('auth.confirmLinkButton', 'Yes, sign in')}
              </button>
              <button
                onClick={handleCancelToken}
                className="w-full py-3 mt-3 text-muted-foreground hover:text-foreground transition-colors"
              >
                {t('cancel')}
              </button>
            </div>
          ) : (
          <>
          {/* Header */}
          <div className="text-center mb-8">
            <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
              <Mail className="w-8 h-8 text-primary" />
            </div>
            <h2 className="text-2xl font-bold text-foreground">
              {magicLinkSent ? t('auth.checkYourEmail') : t('auth.signIn')}
            </h2>
            <p className="text-muted-foreground mt-2">
              {magicLinkSent
                ? t('auth.magicLinkSent')
                : authMode === 'password'
                  ? t('auth.signInWithPassword')
                  : t('auth.withoutPasswords')}
            </p>
          </div>

          {/* Form */}
          {!magicLinkSent ? (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label htmlFor="email" className="block text-sm font-medium text-foreground mb-2">
                  {t('email')}
                </label>
                <input
                  id="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder={t('auth.emailPlaceholder')}
                  className="input w-full"
                  disabled={isSubmitting}
                  autoFocus
                />
              </div>
              {authMode === 'password' && (
                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-foreground mb-2">
                    {t('auth.password')}
                  </label>
                  <input
                    id="password"
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder={t('auth.passwordPlaceholder')}
                    className="input w-full"
                    disabled={isSubmitting}
                  />
                </div>
              )}

              {error && (
                <div className="bg-destructive/10 text-destructive text-sm p-3 rounded-lg">
                  {error}
                </div>
              )}

              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full btn-primary py-3"
              >
                {isSubmitting
                  ? t('auth.sending')
                  : authMode === 'password'
                    ? t('auth.signInWithPassword')
                    : t('auth.sendMagicLink')}
              </button>
              <button
                type="button"
                onClick={() => {
                  setAuthMode(authMode === 'magic' ? 'password' : 'magic');
                  setError('');
                }}
                className="w-full py-2 text-sm text-primary hover:underline"
              >
                {authMode === 'magic' ? t('auth.usePassword') : t('auth.useMagicLink')}
              </button>
            </form>
          ) : (
            <div className="space-y-4">
              <div className="bg-primary/5 border border-primary/20 rounded-lg p-4 space-y-3">
                <div className="flex items-start gap-3">
                  <CheckCircle className="w-5 h-5 text-primary mt-0.5 flex-shrink-0" />
                  <div>
                    <p className="font-medium text-foreground">{t('auth.linkSent')}</p>
                    <p className="text-sm text-muted-foreground">
                      {t('auth.linkSentTo')} <strong>{email}</strong>
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <Clock className="w-5 h-5 text-primary mt-0.5 flex-shrink-0" />
                  <div>
                    <p className="font-medium text-foreground">{t('auth.expiresIn')}</p>
                    <p className="text-sm text-muted-foreground">
                      {t('auth.useLinkBeforeExpires')}
                    </p>
                  </div>
                </div>
              </div>

              <p className="text-sm text-center text-muted-foreground">
                {t('auth.notReceived')}
              </p>

              <button
                onClick={() => setMagicLinkSent(false)}
                className="w-full py-2 text-sm text-primary hover:underline"
              >
                {t('auth.useDifferentEmail')}
              </button>
            </div>
          )}

          {/* Footer */}
          <div className="mt-6 pt-6 border-t border-border text-center text-xs text-muted-foreground">
            {t('acceptTerms')}{' '}
            <a href="/terms" className="text-primary hover:underline" onClick={handleClose}>
              {t('termsOfUse')}
            </a>{' '}
            y{' '}
            <a href="/privacy" className="text-primary hover:underline" onClick={handleClose}>
              {t('privacyPolicy')}
            </a>
          </div>
          </>
          )}
        </div>
      </div>
    </div>
  );
}
