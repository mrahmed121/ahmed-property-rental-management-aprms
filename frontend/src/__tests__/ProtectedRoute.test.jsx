import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProtectedRoute from '../components/ProtectedRoute';
import { useAuth } from '../auth/AuthContext';

vi.mock('../auth/AuthContext', () => ({
  useAuth: vi.fn(),
}));

function renderAt(path, ui) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/login" element={<div>LOGIN PAGE</div>} />
        <Route path="/403" element={<div>FORBIDDEN PAGE</div>} />
        <Route path="/protected" element={ui} />
      </Routes>
    </MemoryRouter>
  );
}

describe('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a spinner while auth is resolving', () => {
    useAuth.mockReturnValue({
      isAuthenticated: false,
      loading: true,
      initialized: false,
      hasPermission: () => false,
    });
    renderAt(
      '/protected',
      <ProtectedRoute>
        <div>SECRET</div>
      </ProtectedRoute>
    );
    expect(screen.getByRole('status')).toBeInTheDocument();
    expect(screen.queryByText('SECRET')).not.toBeInTheDocument();
  });

  it('redirects unauthenticated visitors to /login', () => {
    useAuth.mockReturnValue({
      isAuthenticated: false,
      loading: false,
      initialized: true,
      hasPermission: () => false,
    });
    renderAt(
      '/protected',
      <ProtectedRoute>
        <div>SECRET</div>
      </ProtectedRoute>
    );
    expect(screen.getByText('LOGIN PAGE')).toBeInTheDocument();
    expect(screen.queryByText('SECRET')).not.toBeInTheDocument();
  });

  it('redirects to /403 when the permission is missing', () => {
    useAuth.mockReturnValue({
      isAuthenticated: true,
      loading: false,
      initialized: true,
      hasPermission: () => false,
    });
    renderAt(
      '/protected',
      <ProtectedRoute permission="users.view">
        <div>SECRET</div>
      </ProtectedRoute>
    );
    expect(screen.getByText('FORBIDDEN PAGE')).toBeInTheDocument();
    expect(screen.queryByText('SECRET')).not.toBeInTheDocument();
  });

  it('renders children when authenticated with the permission', () => {
    useAuth.mockReturnValue({
      isAuthenticated: true,
      loading: false,
      initialized: true,
      hasPermission: () => true,
    });
    renderAt(
      '/protected',
      <ProtectedRoute permission="users.view">
        <div>SECRET</div>
      </ProtectedRoute>
    );
    expect(screen.getByText('SECRET')).toBeInTheDocument();
  });

  it('renders children when no permission is required', () => {
    useAuth.mockReturnValue({
      isAuthenticated: true,
      loading: false,
      initialized: true,
      hasPermission: () => false,
    });
    renderAt(
      '/protected',
      <ProtectedRoute>
        <div>SECRET</div>
      </ProtectedRoute>
    );
    expect(screen.getByText('SECRET')).toBeInTheDocument();
  });
});
