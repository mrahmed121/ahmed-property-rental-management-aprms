import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import client, { clearAuth, getToken, setToken } from '../api/client';

const AuthContext = createContext(null);

function extractUser(payload) {
  // Login contract:  { data: { token, user: {...} } }
  // Me contract:     { data: { user: {...} } } or { data: {...user} }
  if (!payload) return { user: null, token: null };
  const data = payload.data ?? payload;
  const token = data.token ?? data.access_token ?? null;
  const user = data.user ?? (data.id || data.email ? data : null);
  return { user, token };
}

function extractPermissions(user) {
  // Backend contract: user.role.permissions = [{slug} | 'slug']
  const perms = user?.role?.permissions ?? user?.permissions ?? [];
  return perms.map((p) => (typeof p === 'string' ? p : p.slug)).filter(Boolean);
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [permissions, setPermissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [initialized, setInitialized] = useState(false);

  const applySession = useCallback((nextUser) => {
    setUser(nextUser);
    setPermissions(extractPermissions(nextUser));
  }, []);

  const fetchMe = useCallback(async () => {
    const token = getToken();
    if (!token) {
      setLoading(false);
      setInitialized(true);
      return null;
    }
    try {
      const res = await client.get('/auth/me');
      const { user: me } = extractUser(res.data);
      applySession(me);
      return me;
    } catch {
      clearAuth();
      applySession(null);
      return null;
    } finally {
      setLoading(false);
      setInitialized(true);
    }
  }, [applySession]);

  useEffect(() => {
    fetchMe();
  }, [fetchMe]);

  const login = useCallback(
    async (email, password) => {
      const res = await client.post('/auth/login', { email, password });
      const { user: loggedIn, token } = extractUser(res.data);
      if (!token || !loggedIn) {
        throw new Error('Invalid login response from server.');
      }
      setToken(token);
      applySession(loggedIn);
      return loggedIn;
    },
    [applySession]
  );

  const logout = useCallback(async () => {
    try {
      await client.post('/auth/logout');
    } catch {
      // Logout is best-effort; always clear local session.
    } finally {
      clearAuth();
      applySession(null);
    }
  }, [applySession]);

  const hasPermission = useCallback(
    (slug) => {
      if (!slug) return true;
      if (Array.isArray(slug)) return slug.some((s) => permissions.includes(s));
      return permissions.includes(slug);
    },
    [permissions]
  );

  const hasRole = useCallback(
    (slug) => {
      if (!slug) return true;
      const roleSlug = user?.role?.slug;
      if (Array.isArray(slug)) return slug.includes(roleSlug);
      return roleSlug === slug;
    },
    [user]
  );

  const value = useMemo(
    () => ({
      user,
      permissions,
      loading,
      initialized,
      isAuthenticated: !!user,
      login,
      logout,
      refresh: fetchMe,
      hasPermission,
      hasRole,
    }),
    [user, permissions, loading, initialized, login, logout, fetchMe, hasPermission, hasRole]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
