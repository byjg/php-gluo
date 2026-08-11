import React, {useState} from 'react';
import {Link, useNavigate} from 'react-router-dom';
import {api} from '@/lib/api';
import {Button, Card, Field, Input} from '@/components/ui';

/**
 * Three-step reset flow:
 *   1. request  -> POST /login/resetrequest  {email}                 -> {token} (+ emailed code)
 *   2. confirm  -> POST /login/confirmcode   {email, token, code}    -> {token}
 *   3. reset    -> POST /login/resetpassword {email, token, password}
 */
export default function ForgotPassword() {
  const navigate = useNavigate();
  const [step, setStep] = useState(1);
  const [email, setEmail] = useState('');
  const [resetToken, setResetToken] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [info, setInfo] = useState('');
  const [loading, setLoading] = useState(false);

  const call = async (fn) => {
    setError('');
    setLoading(true);
    try {
      await fn();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const requestReset = (e) => {
    e.preventDefault();
    call(async () => {
      const data = await api.postJson('/login/resetrequest', { email }, { skipAuthError: true });
      setResetToken(data?.token || '');
      setInfo('If the email exists, a confirmation code was sent to it.');
      setStep(2);
    });
  };

  const confirmCode = (e) => {
    e.preventDefault();
    call(async () => {
      const data = await api.postJson('/login/confirmcode', { email, token: resetToken, code }, { skipAuthError: true });
      setResetToken(data?.token || resetToken);
      setInfo('');
      setStep(3);
    });
  };

  const resetPassword = (e) => {
    e.preventDefault();
    call(async () => {
      await api.postJson('/login/resetpassword', { email, token: resetToken, password }, { skipAuthError: true });
      navigate('/login');
    });
  };

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <Card className="w-full max-w-sm p-8">
        <h1 className="mb-1 text-xl font-bold text-slate-800">Reset password</h1>
        <p className="mb-6 text-sm text-slate-500">Step {step} of 3</p>

        {info && <p className="mb-4 text-sm text-slate-600">{info}</p>}
        {error && <p className="mb-4 text-sm text-red-600">{error}</p>}

        {step === 1 && (
          <form onSubmit={requestReset} className="space-y-4">
            <Field label="Email">
              <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
            </Field>
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? 'Sending…' : 'Send code'}
            </Button>
          </form>
        )}

        {step === 2 && (
          <form onSubmit={confirmCode} className="space-y-4">
            <Field label="Confirmation code">
              <Input value={code} onChange={(e) => setCode(e.target.value)} required />
            </Field>
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? 'Checking…' : 'Confirm code'}
            </Button>
          </form>
        )}

        {step === 3 && (
          <form onSubmit={resetPassword} className="space-y-4">
            <Field label="New password">
              <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
            </Field>
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? 'Saving…' : 'Set new password'}
            </Button>
          </form>
        )}

        <div className="mt-4 text-center text-sm">
          <Link to="/login" className="text-brand hover:underline">
            Back to sign in
          </Link>
        </div>
      </Card>
    </div>
  );
}
