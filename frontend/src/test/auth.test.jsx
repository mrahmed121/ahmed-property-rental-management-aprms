import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

vi.mock('../services/api', () => {
  const post = vi.fn();
  const get = vi.fn();
  const interceptors = { request: { use: vi.fn() }, response: { use: vi.fn() } };
  return {
    default: { post, get, interceptors },
    getToken: vi.fn(() => null),
    setToken: vi.fn(),
  };
});

import api from '../services/api';
import { AuthProvider, useAuth } from '../context/AuthContext';
import Login from '../pages/Login';
import ProtectedRoute from '../components/common/ProtectedRoute';

function renderLogin() {
  return render(
    <MemoryRouter initialEntries={['/login']}>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/" element={<div>Dashboard home</div>} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>
  );
}

describe('Login flow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('renders the login form', () => {
    renderLogin();
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('logs in successfully and navigates to the dashboard', async () => {
    const user = userEvent.setup();
    api.post.mockResolvedValueOnce({
      data: {
        data: {
          token: 'fake-jwt-token',
          user: {
            id: 1, name: 'Agency Admin', email: 'admin@ahmedestates.local',
            roles: ['agency-admin'], permissions: ['dashboard.view'],
            agency: { id: 1, name: 'Ahmed Estates', slug: 'ahmed-estates' },
          },
        },
      },
    });

    renderLogin();

    await user.type(screen.getByLabelText(/email/i), 'admin@ahmedestates.local');
    await user.type(screen.getByLabelText(/password/i), 'password123');
    await user.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/auth/login', {
        email: 'admin@ahmedestates.local',
        password: 'password123',
      });
    });
    await waitFor(() => {
      expect(screen.getByText('Dashboard home')).toBeInTheDocument();
    });
  });

  it('shows an error message on invalid credentials', async () => {
    const user = userEvent.setup();
    api.post.mockRejectedValueOnce({
      response: { data: { message: 'Invalid credentials.' } },
    });

    renderLogin();

    await user.type(screen.getByLabelText(/email/i), 'admin@ahmedestates.local');
    await user.type(screen.getByLabelText(/password/i), 'wrong');
    await user.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Invalid credentials.');
    });
  });

  it('shows validation errors returned by the API', async () => {
    const user = userEvent.setup();
    api.post.mockRejectedValueOnce({
      response: {
        data: {
          message: 'The email field is required.',
          errors: { email: ['The email field is required.'] },
        },
      },
    });

    renderLogin();

    // Fill a valid-format email so native HTML5 validation passes;
    // the API mock then rejects with a validation error.
    await user.type(screen.getByLabelText(/email/i), 'test@example.com');
    await user.type(screen.getByLabelText(/password/i), 'x');
    await user.click(screen.getByRole('button', { name: /sign in/i }));

    await waitFor(() => {
      // The message appears both in the alert banner and under the field.
      expect(screen.getAllByText('The email field is required.').length).toBeGreaterThanOrEqual(1);
    });
  });
});

describe('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('redirects unauthenticated users to /login', async () => {
    api.get.mockRejectedValueOnce({ response: { status: 401 } });

    function Probe() {
      const { loading } = useAuth();
      return <div>{loading ? 'loading' : 'done'}</div>;
    }

    render(
      <MemoryRouter initialEntries={['/']}>
        <AuthProvider>
          <Routes>
            <Route path="/login" element={<div>Login page</div>} />
            <Route
              path="/"
              element={
                <ProtectedRoute>
                  <div>Secret</div>
                </ProtectedRoute>
              }
            />
          </Routes>
          <Probe />
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('Login page')).toBeInTheDocument();
    });
    expect(screen.queryByText('Secret')).not.toBeInTheDocument();
  });
});
