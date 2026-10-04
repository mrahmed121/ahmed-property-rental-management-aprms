import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';
import Button from '../shared/Button';
import ErrorAlert from '../shared/ErrorAlert';
import Input from '../shared/Input';

function friendlyError(err) {
  const status = err?.response?.status;
  const data = err?.response?.data;
  if (status === 401) return 'Invalid email or password.';
  if (status === 422 && data?.errors) {
    const first = Object.values(data.errors)[0];
    return Array.isArray(first) ? first[0] : String(first);
  }
  if (data?.message) return data.message;
  return 'Login failed. Please check your connection and try again.';
}

export default function Login() {
  const { login, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  if (isAuthenticated) {
    const from = location.state?.from || '/';
    navigate(from, { replace: true });
    return null;
  }

  const validate = () => {
    const next = {};
    if (!email.trim()) next.email = 'Email is required.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim()))
      next.email = 'Enter a valid email address.';
    if (!password) next.password = 'Password is required.';
    setErrors(next);
    return Object.keys(next).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setServerError('');
    if (!validate()) return;
    setSubmitting(true);
    try {
      await login(email.trim(), password);
      const from = location.state?.from || '/';
      navigate(from, { replace: true });
    } catch (err) {
      setServerError(friendlyError(err));
    } finally {
      setSubmitting(false);
    }
  };

  const canSubmit = email.trim() !== '' && password !== '' && !submitting;

  return (
    <div className="flex min-h-screen items-center justify-center bg-charcoal-900 px-4">
      <div className="glass w-full max-w-md rounded-2xl p-8 shadow-2xl">
        <div className="mb-8 text-center">
          <h1 className="text-3xl font-bold tracking-wide text-gold">APRMS</h1>
          <p className="mt-2 text-sm text-gray-400">
            Ahmed Property &amp; Rental Management System
          </p>
          <p className="mt-1 text-xs text-copper-light">
            Ahmed — Own Every Square Foot.
          </p>
        </div>

        <form onSubmit={handleSubmit} noValidate>
          <ErrorAlert message={serverError} className="mb-4" />

          <Input
            label="Email"
            name="email"
            type="email"
            autoComplete="email"
            placeholder="you@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={errors.email}
            className="mb-4"
          />

          <Input
            label="Password"
            name="password"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errors.password}
            className="mb-6"
          />

          <Button
            type="submit"
            variant="primary"
            size="lg"
            className="w-full"
            disabled={!canSubmit}
          >
            {submitting ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>

        <p className="mt-8 text-center text-xs text-gray-600">
          Developed by Ahmed
        </p>
      </div>
    </div>
  );
}
