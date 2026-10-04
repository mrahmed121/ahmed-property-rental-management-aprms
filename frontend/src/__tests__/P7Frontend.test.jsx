import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { vi } from 'vitest';
import StatementsPage from '../modules/statements/pages/StatementsPage';
import StatementDetailPage from '../modules/statements/pages/StatementDetailPage';
import OwnerReportsPage from '../modules/statements/pages/OwnerReportsPage';
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
  permissions: ['statements.view', 'statements.generate', 'statements.review', 'statements.approve', 'statements.finalize', 'statements.adjust', 'owner-reports.view'],
  roles: ['agency-admin'],
};

const OWNER = {
  id: 5, name: 'Owner', email: 'owner@ahmedestates.local',
  permissions: ['statements.view', 'owner-reports.view'],
  roles: ['owner'],
};

function mockApi(user, overrides = {}) {
  mockGet.mockImplementation((url) => {
    if (url === '/auth/me') return Promise.resolve({ data: { data: user } });
    if (overrides[url]) return Promise.resolve(overrides[url]);
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  api.post.mockResolvedValue({ data: { data: {} } });
}

function renderAt(path, route, user, overrides, element) {
  mockApi(user, overrides);
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path={route} element={element} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>
  );
}

const SAMPLE_STATEMENT = {
  id: 1, statement_number: 'STMT-2026-000001',
  owner: { id: 5, name: 'Owner' },
  period: { id: 1, start_date: '2026-09-01', end_date: '2026-09-30', status: 'open' },
  currency: 'PKR', status: 'draft',
  gross_income: 100000, management_fee_percent: 10, management_fee: 10000,
  owner_expenses: 15000, owner_maintenance: 5000, owner_utility_absorption: 2000,
  adjustments_total: 3000, net_amount: 71000,
  lines: [
    { id: 1, line_type: 'income', description: 'Rent invoice', line_date: '2026-09-01', amount: 100000, property: 'Sunset', reference: 'LEDGER-1' },
    { id: 2, line_type: 'management_fee', description: 'Management fee 10%', line_date: '2026-09-30', amount: -10000, property: null, reference: 'FEE-10PCT' },
  ],
  adjustments: [],
};

describe('P7 Statements frontend', () => {
  beforeEach(() => vi.clearAllMocks());

  test('statement list renders rows', async () => {
    renderAt('/statements', '/statements', ADMIN, {
      '/owner-statements': { data: { data: [SAMPLE_STATEMENT], meta: { current_page: 1, total: 1 } } },
    }, <StatementsPage />);

    await waitFor(() => expect(screen.getByText('STMT-2026-000001')).toBeInTheDocument());
  });

  test('statement list empty state', async () => {
    renderAt('/statements', '/statements', ADMIN, {
      '/owner-statements': { data: { data: [], meta: { current_page: 1, total: 0 } } },
    }, <StatementsPage />);

    await waitFor(() => expect(screen.getByText(/No owner statements yet/)).toBeInTheDocument());
  });

  test('statement detail shows reconciliation', async () => {
    renderAt('/statements/1', '/statements/:id', ADMIN, {
      '/owner-statements/1': { data: { data: SAMPLE_STATEMENT } },
    }, <StatementDetailPage />);

    await waitFor(() => expect(screen.getByText('STMT-2026-000001')).toBeInTheDocument());
    expect(screen.getByText('Reconciliation')).toBeInTheDocument();
    expect(screen.getByText('Statement Lines (2)')).toBeInTheDocument();
  });

  test('statement detail shows approve workflow for review status', async () => {
    const reviewStmt = { ...SAMPLE_STATEMENT, status: 'review' };
    renderAt('/statements/1', '/statements/:id', ADMIN, {
      '/owner-statements/1': { data: { data: reviewStmt } },
    }, <StatementDetailPage />);

    await waitFor(() => expect(screen.getByText('Approve')).toBeInTheDocument());
  });

  test('owner without generate permission sees no generate button', async () => {
    renderAt('/statements', '/statements', OWNER, {
      '/owner-statements': { data: { data: [SAMPLE_STATEMENT], meta: { current_page: 1, total: 1 } } },
    }, <StatementsPage />);

    await waitFor(() => expect(screen.getByText('STMT-2026-000001')).toBeInTheDocument());
    expect(screen.queryByText('Generate Statement')).not.toBeInTheDocument();
  });

  test('owner reports render portfolio', async () => {
    renderAt('/statements/reports', '/statements/reports', OWNER, {
      '/owner-reports/portfolio': { data: { data: {
        owner: { id: 5, name: 'Owner' },
        properties: [{ id: 1, name: 'Sunset', units: 10, active_leases: 8, occupancy_rate: 80 }],
        property_count: 1,
        statements: { total: 1, finalized: 1, pending: 0, total_net: 71000 },
      } } },
      '/owner-reports/profitability': { data: { data: { properties: [], currency: 'PKR' } } },
      '/owner-reports/trend': { data: { data: { trend: [], currency: 'PKR' } } },
    }, <OwnerReportsPage />);

    await waitFor(() => expect(screen.getByText(/Owner Reports/)).toBeInTheDocument());
    expect(screen.getByText('Sunset')).toBeInTheDocument();
  });
});
