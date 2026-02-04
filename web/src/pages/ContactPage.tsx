/**
 * Contact page with honeypot.
 */

import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Mail } from 'lucide-react';
import { buildApiUrl } from '@/lib/api/base';

type FormStatus = 'idle' | 'sending' | 'sent' | 'error';

export function ContactPage() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<FormStatus>('idle');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setStatus('sending');
    setErrorMessage(null);

    const formData = new FormData(event.currentTarget);
    const payload = {
      name: String(formData.get('name') || '').trim(),
      email: String(formData.get('email') || '').trim(),
      subject: String(formData.get('subject') || '').trim(),
      message: String(formData.get('message') || '').trim(),
      website: String(formData.get('website') || '').trim(),
    };

    try {
      const response = await fetch(buildApiUrl('/contact'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        const raw = await response.text();
        const contentType = response.headers.get('content-type') || '';
        let message: string | null = null;

        if (raw && contentType.includes('application/json')) {
          try {
            const parsed = JSON.parse(raw) as { message?: string };
            message = parsed?.message ?? null;
          } catch {
            message = null;
          }
        }

        throw new Error(message || t('contact.errorGeneric'));
      }

      setStatus('sent');
      event.currentTarget.reset();
    } catch (error) {
      setStatus('error');
      setErrorMessage(error instanceof Error ? error.message : t('contact.errorGeneric'));
    }
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-primary rounded-lg flex items-center justify-center shadow-lg">
            <Mail className="w-7 h-7 text-white" />
          </div>
          <div>
            <h1 className="text-3xl font-bold text-foreground">
              {t('contact.title')}
            </h1>
            <p className="text-muted-foreground">
              {t('contact.subtitle')}
            </p>
          </div>
        </div>
      </div>

      <div className="bg-card rounded-xl shadow-sm border border-border p-8">
        <form onSubmit={handleSubmit} className="space-y-5">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <label htmlFor="name" className="text-sm font-medium text-foreground">{t('contact.name')}</label>
              <input
                id="name"
                name="name"
                type="text"
                className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                placeholder={t('contact.namePlaceholder')}
                autoComplete="name"
              />
            </div>
            <div className="space-y-2">
              <label htmlFor="email" className="text-sm font-medium text-foreground">{t('contact.email')}</label>
              <input
                id="email"
                name="email"
                type="email"
                required
                className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
                placeholder={t('contact.emailPlaceholder')}
                autoComplete="email"
              />
            </div>
          </div>

          <div className="space-y-2">
            <label htmlFor="subject" className="text-sm font-medium text-foreground">{t('contact.subject')}</label>
            <input
              id="subject"
              name="subject"
              type="text"
              className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
              placeholder={t('contact.subjectPlaceholder')}
            />
          </div>

          <div className="space-y-2">
            <label htmlFor="message" className="text-sm font-medium text-foreground">{t('contact.message')}</label>
            <textarea
              id="message"
              name="message"
              required
              rows={6}
              className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm"
              placeholder={t('contact.messagePlaceholder')}
            />
          </div>

          {/* Honeypot */}
          <div className="hidden">
            <label htmlFor="website">Website</label>
            <input id="website" name="website" type="text" tabIndex={-1} autoComplete="off" />
          </div>

          {status === 'sent' && (
            <div className="text-sm text-emerald-600">{t('contact.sent')}</div>
          )}
          {status === 'error' && (
            <div className="text-sm text-destructive">{errorMessage}</div>
          )}

          <div className="flex justify-end">
            <button
              type="submit"
              disabled={status === 'sending'}
              className="inline-flex items-center justify-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
            >
              {status === 'sending' ? t('contact.sending') : t('contact.send')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
