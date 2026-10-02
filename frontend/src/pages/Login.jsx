import { useState } from 'react';
import { Navigate, useLocation, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import Spinner from '../components/common/Spinner';

export default function Login() {
  const { user, loading, login, authError } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [searchParams] = useSearchParams();
  const location = useLocation();

  if (!loading && user) {
    const from = location.state?.from?.pathname || '/';
    return <Navigate to={from} replace />;
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setErrors(null);
    const res = await login(email.trim(), password);
    setSubmitting(false);
    if (!res.ok) setErrors(res.errors);
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-charcoal-950 px-4">
      <div className="w-full max-w-md">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gold/15 text-3xl ring-1 ring-gold/40">
            🏢
          </div>
          <h1 className="text-2xl font-bold text-slate-100">APRMS</h1>
          <p className="mt-1 text-sm text-slate-400">
            Ahmed Property & Rental Management System
          </p>
          <p className="mt-0.5 text-xs italic text-gold/80">“Ahmed — Own Every Square Foot.”</p>
        </div>

        <form onSubmit={handleSubmit} className="aprms-card space-y-4" noValidate>
          {searchParams.get('expired') && (
            <p className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
              Your session expired. Please sign in again.
            </p>
          )}
          {authError && (
            <p role="alert" className="rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-300">
              {authError}
            </p>
          )}

          <div>
            <label htmlFor="email" className="aprms-label">Email</label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              className="aprms-input"
              placeholder="you@agency.com"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
            {errors?.email && <p className="aprms-error">{errors.email[0]}</p>}
          </div>

          <div>
            <label htmlFor="password" className="aprms-label">Password</label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              className="aprms-input"
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            {errors?.password && <p className="aprms-error">{errors.password[0]}</p>}
          </div>

          <button type="submit" className="aprms-btn-gold w-full" disabled={submitting}>
            {submitting ? <Spinner label="Signing in…" /> : 'Sign in'}
          </button>
        </form>

        <p className="mt-6 text-center text-[11px] text-slate-600">Developed by Ahmed</p>
      </div>
    </div>
  );
}
