import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import api, { getToken, setToken } from '../services/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [authError, setAuthError] = useState(null);

  const fetchMe = useCallback(async () => {
    if (!getToken()) {
      setLoading(false);
      return null;
    }
    try {
      const { data } = await api.get('/me');
      setUser(data.data);
      return data.data;
    } catch {
      setUser(null);
      return null;
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchMe();
  }, [fetchMe]);

  const login = useCallback(async (email, password) => {
    setAuthError(null);
    try {
      const { data } = await api.post('/auth/login', { email, password });
      setToken(data.data.token);
      setUser(data.data.user);
      return { ok: true };
    } catch (err) {
      const msg =
        err.response?.data?.message || 'Login failed. Please check your credentials.';
      const errors = err.response?.data?.errors || null;
      setAuthError(msg);
      return { ok: false, message: msg, errors };
    }
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      /* token may already be invalid — still clear local state */
    } finally {
      setToken(null);
      setUser(null);
    }
  }, []);

  const hasPermission = useCallback(
    (permission) => {
      if (!user) return false;
      return (user.permissions || []).includes(permission);
    },
    [user]
  );

  const hasRole = useCallback(
    (role) => {
      if (!user) return false;
      return (user.roles || []).includes(role);
    },
    [user]
  );

  const value = useMemo(
    () => ({ user, loading, authError, login, logout, fetchMe, hasPermission, hasRole }),
    [user, loading, authError, login, logout, fetchMe, hasPermission, hasRole]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
};
