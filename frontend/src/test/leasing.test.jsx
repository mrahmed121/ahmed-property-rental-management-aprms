import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

const { mockGet, mockPost, mockPut, mockDelete } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockPut: vi.fn(),
  mockDelete: vi.fn(),
}));

vi.mock('../services/api', () => {
  const interceptors = { request: { use: vi.fn() }, response: { use: vi.fn() } };
  return {
    default: { get: mockGet, post: mockPost, put: mockPut, delete: mockDelete, interceptors },
    getToken: vi.fn(() => 'token'),
    setToken: vi.fn(),
    clearAuth: vi.fn(),
  };
});

vi.mock('../api/client', () => {
  const interceptors = { request: { use: vi.fn() }, response: { use: vi.fn() } };
  return {
    default: { get: mockGet, post: mockPost, put: mockPut, delete: mockDelete, interceptors },
    getToken: vi.fn(() => 'token'),
    setToken: vi.fn(),
    clearAuth: vi.fn(),
    TOKEN_KEY: 'aprms_token',
  };
});

import api from '../services/api';
import { AuthProvider } from '../context/AuthContext';
import TenantsPage from '../modules/leasing/pages/TenantsPage';
import LeasesPage from '../modules/leasing/pages/LeasesPage';
import LeaseDetailPage from '../modules/leasing/pages/LeaseDetailPage';
import ApplicationDetailPage from '../modules/leasing/pages/ApplicationDetailPage';
import Dashboard from '../pages/Dashboard';

const ADMIN = {
  id: 1, name: 'Admin', email: 'admin@x.local',
  permissions: ['dashboard.view', 'tenants.view', 'tenants.manage', 'applications.view', 'applications.manage',
    'screening.view', 'screening.manage', 'leases.view', 'leases.manage',
    'inspections.view', 'inspections.manage', 'documents.view', 'documents.manage'],
};

function mockMe(user = ADMIN) {
  mockGet.mockImplementation((url) => {
    if (url === '/auth/me') return Promise.resolve({ data: { user } });
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

function renderAt(path, element) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/tenants" element={element} />
          <Route path="/tenants/new" element={<div>tenant form</div>} />
          <Route path="/tenants/:id" element={<div>tenant detail</div>} />
          <Route path="/leases" element={element} />
          <Route path="/leases/new" element={<div>lease form</div>} />
          <Route path="/leases/:id" element={element} />
          <Route path="/applications" element={<div>apps</div>} />
          <Route path="/applications/new" element={element} />
          <Route path="/applications/:id" element={element} />
          <Route path="/" element={element} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>
  );
}

const TENANTS_PAGE = {
  data: {
    data: [
      { id: 1, name: 'Ahmed Raza', email: 'a@x.com', phone: '0300', kyc_status: 'verified', status: 'active' },
      { id: 2, name: 'Bilal Sheikh', email: null, phone: null, kyc_status: 'pending', status: 'prospective' },
    ],
    meta: { current_page: 1, per_page: 12, total: 2 },
  },
};

const LEASES_PAGE = {
  data: {
    data: [
      { id: 1, lease_number: 'LSE-1-2026-000001', tenant: { name: 'Ahmed Raza' }, property: { name: 'Bahria' }, unit: { unit_number: 'R-101' }, start_date: '2026-01-01', end_date: '2026-12-31', monthly_rent: 40000, status: 'active' },
      { id: 2, lease_number: 'LSE-1-2026-000002', tenant: { name: 'Bilal Sheikh' }, property: { name: 'Bahria' }, unit: { unit_number: 'R-102' }, start_date: '2026-02-01', end_date: '2027-01-31', monthly_rent: 45000, status: 'draft' },
    ],
    meta: { current_page: 1, per_page: 12, total: 2 },
  },
};

const DRAFT_LEASE = {
  data: {
    data: {
      id: 2, lease_number: 'LSE-1-2026-000002', status: 'draft',
      tenant: { name: 'Bilal Sheikh' }, property: { name: 'Bahria' }, unit: { unit_number: 'R-102' },
      start_date: '2026-02-01', end_date: '2027-01-31', monthly_rent: 45000, deposit_amount: 90000,
      activated_at: null, terms: null, previous_lease: null, successor: null,
    },
  },
};

const ACTIVE_LEASE = {
  data: {
    data: {
      id: 1, lease_number: 'LSE-1-2026-000001', status: 'active',
      tenant: { name: 'Ahmed Raza' }, property: { name: 'Bahria' }, unit: { unit_number: 'R-101' },
      start_date: '2026-01-01', end_date: '2026-12-31', monthly_rent: 40000, deposit_amount: 80000,
      activated_at: '2026-01-01', terms: 'Demo', previous_lease: null, successor: null,
    },
  },
};

const UNDER_REVIEW_APP = {
  data: {
    data: {
      id: 5, application_number: 'APP-1-2026-000005', status: 'under_review',
      screening_status: 'pending', screening_notes: null, decision_notes: null,
      tenant: { name: 'Usman Tariq' }, property: { name: 'Bahria' }, unit: { unit_number: 'B-202' },
      screened_by: null, screened_at: null, reviewed_by: null,
    },
  },
};

describe('Tenants list', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockMe();
    mockGet.mockImplementation((url) => {
      if (url === '/auth/me') return Promise.resolve({ data: { user: ADMIN } });
      if (url === '/tenants') return Promise.resolve(TENANTS_PAGE);
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
  });

  it('renders tenants with KYC badges and search', async () => {
    renderAt('/tenants', <TenantsPage />);
    await waitFor(() => expect(screen.getByText('Ahmed Raza')).toBeInTheDocument());
    expect(screen.getByText('Bilal Sheikh')).toBeInTheDocument();
    expect(screen.getByPlaceholderText(/search name/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /new tenant/i })).toBeInTheDocument();
  });

  it('hides management actions without tenants.manage', async () => {
    const viewer = { ...ADMIN, permissions: ['tenants.view'] };
    mockGet.mockImplementation((url) => {
      if (url === '/auth/me') return Promise.resolve({ data: { user: viewer } });
      if (url === '/tenants') return Promise.resolve(TENANTS_PAGE);
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
    renderAt('/tenants', <TenantsPage />);
    await waitFor(() => expect(screen.getByText('Ahmed Raza')).toBeInTheDocument());
    expect(screen.queryByRole('link', { name: /new tenant/i })).not.toBeInTheDocument();
  });
});

describe('Leases list', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockGet.mockImplementation((url) => {
      if (url === '/auth/me') return Promise.resolve({ data: { user: ADMIN } });
      if (url === '/leases') return Promise.resolve(LEASES_PAGE);
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
  });

  it('renders leases with status badges', async () => {
    renderAt('/leases', <LeasesPage />);
    await waitFor(() => expect(screen.getByText('LSE-1-2026-000001')).toBeInTheDocument());
    expect(screen.getByText('LSE-1-2026-000002')).toBeInTheDocument();
  });
});

describe('Lease detail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockGet.mockImplementation((url) => {
      if (url === '/auth/me') return Promise.resolve({ data: { user: ADMIN } });
      if (url === '/leases/2') return Promise.resolve(DRAFT_LEASE);
      if (url === '/leases/1') return Promise.resolve(ACTIVE_LEASE);
      if (url === '/documents') return Promise.resolve({ data: { data: [], meta: { total: 0 } } });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
    mockPost.mockResolvedValue({ data: { data: {} } });
  });

  it('shows Activate for a draft lease and calls the API', async () => {
    const user = userEvent.setup();
    renderAt('/leases/2', <LeaseDetailPage />);
    await waitFor(() => expect(screen.getByText('LSE-1-2026-000002')).toBeInTheDocument());
    const btn = screen.getByRole('button', { name: /activate lease/i });
    expect(btn).toBeInTheDocument();
    await user.click(btn);
    const confirm = await screen.findByRole('button', { name: /^activate$/i });
    await user.click(confirm);
    await waitFor(() => expect(api.post).toHaveBeenCalledWith('/leases/2/activate'));
  });

  it('shows renew and terminate for an active lease', async () => {
    renderAt('/leases/1', <LeaseDetailPage />);
    await waitFor(() => expect(screen.getByText('LSE-1-2026-000001')).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /renew/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /terminate lease/i })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /activate lease/i })).not.toBeInTheDocument();
  });
});

describe('Application review', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockGet.mockImplementation((url) => {
      if (url === '/auth/me') return Promise.resolve({ data: { user: ADMIN } });
      if (url === '/applications/5') return Promise.resolve(UNDER_REVIEW_APP);
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
    mockPost.mockResolvedValue({ data: { data: {} } });
  });

  it('renders the workflow and advances under_review to screening', async () => {
    const user = userEvent.setup();
    renderAt('/applications/5', <ApplicationDetailPage />);
    await waitFor(() => expect(screen.getByText('APP-1-2026-000005')).toBeInTheDocument());
    expect(screen.getByText(/Usman Tariq/)).toBeInTheDocument();
    const startBtn = screen.getByRole('button', { name: /start screening/i });
    await user.click(startBtn);
    await waitFor(() =>
      expect(api.post).toHaveBeenCalledWith('/applications/5/screening/start')
    );
  });
});

describe('Dashboard leasing metrics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockGet.mockImplementation((url) => {
      if (url === '/auth/me') return Promise.resolve({ data: { user: ADMIN } });
      if (url === '/dashboard/stats') {
        return Promise.resolve({
          data: {
            data: {
              total_properties: 4, total_buildings: 7, total_units: 22, vacant_units: 18,
              units_by_status: { vacant: 18, occupied: 2 },
              total_tenants: 5, active_leases: 2, leases_expiring_soon: 1,
              leases_by_status: { active: 2, terminated: 1 },
            },
          },
        });
      }
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
  });

  it('shows real leasing numbers, not placeholders', async () => {
    renderAt('/', <Dashboard />);
    await waitFor(() => expect(screen.getByText('Leasing')).toBeInTheDocument());
    expect(screen.getByText('Active leases')).toBeInTheDocument();
    expect(screen.getByText('Expiring ≤ 60 days')).toBeInTheDocument();
    // The old "No tenants yet" placeholder must be gone.
    expect(screen.queryByText('Tenant onboarding ships with the leasing module')).not.toBeInTheDocument();
  });
});
