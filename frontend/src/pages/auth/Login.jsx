import React, {useState} from 'react';
import {Link, useNavigate} from 'react-router-dom';
import {api} from '@/lib/api';
import {useAuth} from '@/context/AuthContext';
import {Button, Card, Field, Input} from '@/components/ui';

export default function Login() {
  const navigate = useNavigate();
  const { login } = useAuth();
  const [username, setUsername] = useState('admin@example.com');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      const data = await api.postJson('/login', { username, password }, { skipAuthError: true });
      if (!data?.token) throw new Error('Login failed');
      login(data.token, data.data);
      navigate('/dashboard');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-sm p-8">
        <h1 className="mb-1 text-2xl font-bold text-brand">Gluo</h1>
        <p className="mb-6 text-sm text-slate-500">Sign in to your account</p>
        <form onSubmit={onSubmit} className="space-y-4">
          <Field label="Email">
            <Input type="email" value={username} onChange={(e) => setUsername(e.target.value)} required />
          </Field>
          <Field label="Password">
            <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </Field>
          {error && <p className="text-sm text-red-600">{error}</p>}
          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>
        <div className="mt-4 text-center text-sm">
          <Link to="/forgot-password" className="text-brand hover:underline">
            Forgot password?
          </Link>
        </div>
      </Card>
    </div>
  );
}
