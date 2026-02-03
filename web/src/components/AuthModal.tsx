/**
 * Authentication modal with magic link flow.
 */

import { useState, useEffect } from 'react';
import { X, Mail, CheckCircle, Clock } from 'lucide-react';
import { useAuth } from '@/contexts/AuthContext';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export function AuthModal({ isOpen, onClose }: AuthModalProps) {
  const { t } = useTranslation();
  const { signIn, verifyToken, magicLinkSent, setMagicLinkSent } = useAuth();
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  // Check for token in URL on mount
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');

    if (token) {
      handleVerifyToken(token);
    }
  }, []);

  const handleVerifyToken = async (token: string) => {
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
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!email || !email.includes('@')) {
      setError(t('auth.invalidEmail'));
      return;
    }

    setIsSubmitting(true);

    try {
      await signIn(email);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    onClose();
    setMagicLinkSent(false);
    setEmail('');
    setError('');
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md relative">
        {/* Close button */}
        <button
          onClick={handleClose}
          className="absolute top-4 right-4 p-2 hover:bg-muted rounded-lg transition-colors"
          aria-label={t('close')}
        >
          <X className="w-5 h-5" />
        </button>

        <div className="p-8">
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
                {isSubmitting ? t('auth.sending') : t('auth.sendMagicLink')}
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
        </div>
      </div>
    </div>
  );
}
