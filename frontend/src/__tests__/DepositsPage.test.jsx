import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { vi } from 'vitest';
import DepositsPage from '../modules/deposits/pages/DepositsPage';
import { AuthProvider } from '../context/AuthContext';
import api from '../services/api';

const { mockGet, mockPost, mockPut, mockDelete } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
  mockDelete: vi.fn(),
}));

vi.mock('../services/api', () => ({
  default: { get: mockGet, post: mockPost, put: mockPut, delete: mockDelete },
  getToken: () => 'test-token',
  setToken: vi.fn(),
  clearAuth: vi.fn(),
}));

vi.mock('../api/client', () => ({
  default: { get: mockGet, post: mockPost, put: mockPut, delete: mockDelete },
  getToken: () => 'test-token',
  setToken: vi.fn(),
  clearAuth: vi.fn(),
  TOKEN_KEY: 'aprms_token',
}));

const ADMIN = {
  id: 1, name: 'Admin', email: 'admin@ahmedestates.local',
  permissions: ['deposits.view', 'deposits.manage', 'deposits.settle'],
};

const VIEWER = {
  id: 2, name: 'Viewer', email: 'viewer@ahmedestates.local',
  permissions: ['deposits.view'],
};

function mockApi(user, overrides = {}) {
  mockGet.mockImplementation((url) => {
    if (url === '/auth/me') return Promise.resolve({ data: { data: user } });
    if (overrides[url]) return Promise.resolve(overrides[url]);
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

function renderAt(user, overrides) {
  mockApi(user, overrides);
  return render(
    <MemoryRouter initialEntries={['/deposits']}>
      <AuthProvider>
        <Routes>
          <Route path="/deposits" element={<DepositsPage />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>
  );
}

const DEPOSITS_PAGE = {
  data: {
    data: [
      {
        id: 1,
        tenant: { id: 1, name: 'Ahmed Raza' },
        property: { id: 1, name: 'Gulshan Heights' },
        unit: { id: 1, unit_number: 'A-101' },
        deposit_amount: 80000,
        held_amount: 80000,
        status: 'held',
      },
    ],
    meta: { current_page: 1, per_page: 12, total: 1 },
  },
};

const EMPTY_PAGE = {
  data: { data: [], meta: { current_page: 1, per_page: 12, total: 0 } },
};

describe('DepositsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('deposit list renders', async () => {
    renderAt(ADMIN, { '/deposits': DEPOSITS_PAGE });

    await waitFor(() => {
      expect(screen.getByText('Ahmed Raza')).toBeInTheDocument();
    });
    expect(screen.getByText('Security Deposits')).toBeInTheDocument();
  });

  test('empty state shows guidance', async () => {
    renderAt(ADMIN, { '/deposits': EMPTY_PAGE });

    await waitFor(() => {
      expect(screen.getByText('No deposits yet')).toBeInTheDocument();
    });
    expect(screen.getByText(/cap is 3× monthly rent/i)).toBeInTheDocument();
  });

  test('permission restriction hides create button', async () => {
    renderAt(VIEWER, { '/deposits': EMPTY_PAGE });

    await waitFor(() => {
      expect(screen.getByText('Security Deposits')).toBeInTheDocument();
    });
    expect(screen.queryByText('+ New Deposit')).not.toBeInTheDocument();
  });
});
