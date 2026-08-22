import React, {useEffect, useState} from 'react';
import {api} from '@/lib/api';
import {Button, Card, Field, Input} from '@/components/ui';

// Stored as a user property (users_property table), constrained to these values.
const LANGUAGES = [
  { value: 'en', label: 'English' },
  { value: 'fr', label: 'French' },
  { value: 'pt', label: 'Portuguese' },
];

export default function Profile() {
  const [profile, setProfile] = useState(null);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [language, setLanguage] = useState('en');
  const [status, setStatus] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const data = await api.request('/profile', { method: 'GET' });
        setProfile(data);
        setName(data.name || '');
        setEmail(data.email || '');
        setLanguage(data.language || 'en');
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const onSave = async (e) => {
    e.preventDefault();
    setStatus('');
    setError('');
    try {
      await api.putJson('/profile', { name, email, language });
      setStatus('Saved.');
    } catch (err) {
      setError(err.message);
    }
  };

  if (loading) return <p className="text-slate-500">Loading…</p>;

  return (
    <Card className="max-w-md p-6">
      <h1 className="mb-4 text-xl font-bold text-slate-800">Your Profile</h1>
      {error && <p className="mb-4 text-sm text-red-600">{error}</p>}
      <form onSubmit={onSave} className="space-y-4">
        <Field label="User ID">
          <Input value={profile?.userid || ''} disabled />
        </Field>
        <Field label="Name">
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </Field>
        <Field label="Email">
          <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
        </Field>
        <Field label="Language" hint="Stored as a user property.">
          <select
            value={language}
            onChange={(e) => setLanguage(e.target.value)}
            className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-brand focus:ring-1 focus:ring-brand"
          >
            {LANGUAGES.map((lang) => (
              <option key={lang.value} value={lang.value}>
                {lang.label}
              </option>
            ))}
          </select>
        </Field>
        <div className="flex items-center gap-3">
          <Button type="submit">Save changes</Button>
          {status && <span className="text-sm text-green-600">{status}</span>}
        </div>
      </form>
    </Card>
  );
}
