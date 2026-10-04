import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider, useAuth } from '../auth/AuthContext';

vi.mock('../api/client', () => {
  const mockClient = { get: vi.fn(), post: vi.fn() };
  return {
    __esModule: true,
    default: mockClient,
    getToken: vi.fn(),
    setToken: vi.fn(),
    clearAuth: vi.fn(),
  };
});

import client, { clearAuth, getToken, setToken } from '../api/client';

const demoUser = {
  id: 1,
  name: 'Ayesha Khan',
  email: 'ayesha@example.com',
  role: { slug: 'agency_admin', name: 'Agency Admin', permissions: ['users.view', 'users.manage'] },
  agency: { id: 1, name: 'Lahore Prime Properties' },
};

function Probe({ onReady }) {
  const auth = useAuth();
  if (onReady) onReady(auth);
  return (
    <div>
      <span data-testid="user">{auth.user ? auth.user.name : 'none'}</span>
      <span data-testid="auth">{auth.isAuthenticated ? 'yes' : 'no'}</span>
      <span data-testid="loading">{auth.loading ? 'loading' : 'ready'}</span>
    </div>
  );
}

describe('AuthContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getToken.mockReturnValue(null);
    client.get.mockResolvedValue({ data: { data: { user: demoUser } } });
    client.post.mockResolvedValue({ data: {} });
  });

  it('starts unauthenticated when no token exists', async () => {
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    );
    await waitFor(() =>
      expect(screen.getByTestId('loading')).toHaveTextContent('ready')
    );
    expect(screen.getByTestId('auth')).toHaveTextContent('no');
    expect(client.get).not.toHaveBeenCalled();
  });

  it('restores the session from a stored token via /auth/me', async () => {
    getToken.mockReturnValue('stored-token');
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    );
    await waitFor(() =>
      expect(screen.getByTestId('user')).toHaveTextContent('Ayesha Khan')
    );
    expect(client.get).toHaveBeenCalledWith('/auth/me');
    expect(screen.getByTestId('auth')).toHaveTextContent('yes');
  });

  it('login stores the token and sets the user', async () => {
    client.post.mockResolvedValue({
      data: { data: { token: 'jwt-123', user: demoUser } },
    });
    let auth;
    render(
      <AuthProvider>
        <Probe onReady={(a) => (auth = a)} />
      </AuthProvider>
    );
    await waitFor(() =>
      expect(screen.getByTestId('loading')).toHaveTextContent('ready')
    );
    await auth.login('ayesha@example.com', 'secret123');
    expect(client.post).toHaveBeenCalledWith('/auth/login', {
      email: 'ayesha@example.com',
      password: 'secret123',
    });
    expect(setToken).toHaveBeenCalledWith('jwt-123');
    await waitFor(() =>
      expect(screen.getByTestId('user')).toHaveTextContent('Ayesha Khan')
    );
  });

  it('hasPermission and hasRole reflect the user session', async () => {
    getToken.mockReturnValue('stored-token');
    let auth;
    render(
      <AuthProvider>
        <Probe onReady={(a) => (auth = a)} />
      </AuthProvider>
    );
    await waitFor(() =>
      expect(screen.getByTestId('user')).toHaveTextContent('Ayesha Khan')
    );
    expect(auth.hasPermission('users.view')).toBe(true);
    expect(auth.hasPermission('leases.view')).toBe(false);
    expect(auth.hasPermission(['leases.view', 'users.manage'])).toBe(true);
    expect(auth.hasPermission(null)).toBe(true);
    expect(auth.hasRole('agency_admin')).toBe(true);
    expect(auth.hasRole('tenant')).toBe(false);
    expect(auth.hasRole(['tenant', 'agency_admin'])).toBe(true);
  });

  it('logout calls the API and clears the session', async () => {
    getToken.mockReturnValue('stored-token');
    let auth;
    render(
      <AuthProvider>
        <Probe onReady={(a) => (auth = a)} />
      </AuthProvider>
    );
    await waitFor(() =>
      expect(screen.getByTestId('user')).toHaveTextContent('Ayesha Khan')
    );
    await auth.logout();
    expect(client.post).toHaveBeenCalledWith('/auth/logout');
    expect(clearAuth).toHaveBeenCalled();
    await waitFor(() =>
      expect(screen.getByTestId('auth')).toHaveTextContent('no')
    );
  });

  it('clears a broken session when /auth/me fails', async () => {
    getToken.mockReturnValue('bad-token');
    client.get.mockRejectedValue({ response: { status: 401 } });
    render(
      <AuthProvider>
        <Probe />
      </AuthProvider>
    );
    await waitFor(() =>
      expect(screen.getByTestId('loading')).toHaveTextContent('ready')
    );
    expect(clearAuth).toHaveBeenCalled();
    expect(screen.getByTestId('auth')).toHaveTextContent('no');
  });
});
