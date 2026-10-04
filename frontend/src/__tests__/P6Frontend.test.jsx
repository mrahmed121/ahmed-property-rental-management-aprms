import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { vi } from 'vitest';
import MetersPage from '../modules/utilities/pages/MetersPage';
import UtilityBillsPage from '../modules/utilities/pages/UtilityBillsPage';
import ExpensesPage from '../modules/expenses/pages/ExpensesPage';
import ExpenseDetailPage from '../modules/expenses/pages/ExpenseDetailPage';
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
  permissions: ['utilities.view', 'utilities.manage', 'utilities.bill', 'expenses.view', 'expenses.create', 'expenses.approve', 'expenses.post'],
};

const TENANT = {
  id: 8, name: 'Tenant', email: 'tenant@ahmedestates.local',
  permissions: ['utilities.view'],
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

describe('P6 Utilities frontend', () => {
  beforeEach(() => vi.clearAllMocks());

  test('meters list renders rows', async () => {
    renderAt('/utilities/meters', '/utilities/meters', ADMIN, {
      '/utility/meters': { data: { data: [
        { id: 1, meter_number: 'EL-001', utility_type: 'electricity', status: 'active', property: { name: 'Sunset' }, unit: null },
      ], meta: { current_page: 1, total: 1 } } },
    }, <MetersPage />);

    await waitFor(() => expect(screen.getByText('EL-001')).toBeInTheDocument());
  });

  test('meters empty state', async () => {
    renderAt('/utilities/meters', '/utilities/meters', ADMIN, {
      '/utility/meters': { data: { data: [], meta: { current_page: 1, total: 0 } } },
    }, <MetersPage />);

    await waitFor(() => expect(screen.getByText(/No meters registered yet/)).toBeInTheDocument());
  });

  test('bills list renders with owner-absorbed label', async () => {
    renderAt('/utilities/bills', '/utilities/bills', ADMIN, {
      '/utility/bills': { data: { data: [
        { id: 1, bill_number: 'UB-2026-000001', meter: { meter_number: 'WA-001', utility_type: 'water' }, period_start: '2026-09-01', period_end: '2026-09-30', consumption: 60, total: 6000, tenant: null, status: 'finalized' },
      ], meta: { current_page: 1, total: 1 } } },
    }, <UtilityBillsPage />);

    await waitFor(() => expect(screen.getByText('UB-2026-000001')).toBeInTheDocument());
    expect(screen.getByText(/Owner absorbed/)).toBeInTheDocument();
  });

  test('tenant cannot see register-meter button', async () => {
    renderAt('/utilities/meters', '/utilities/meters', TENANT, {
      '/utility/meters': { data: { data: [], meta: { current_page: 1, total: 0 } } },
    }, <MetersPage />);

    await waitFor(() => expect(screen.getByText(/No meters registered yet/)).toBeInTheDocument());
    expect(screen.queryByText('Register Meter')).not.toBeInTheDocument();
  });
});

describe('P6 Expenses frontend', () => {
  beforeEach(() => vi.clearAllMocks());

  test('expenses list renders with summary', async () => {
    renderAt('/expenses', '/expenses', ADMIN, {
      '/expenses': { data: { data: [
        { id: 1, expense_number: 'EXP-2026-000001', property: { name: 'Sunset' }, category: 'repairs', description: 'Fix door', expense_date: '2026-09-20', amount: 15000, status: 'posted' },
      ], meta: { current_page: 1, total: 1 } } },
      '/expenses/summary': { data: { data: { total_expenses: 15000, pending_approvals: 0, draft_count: 0, posted_count: 1 } } },
    }, <ExpensesPage />);

    await waitFor(() => expect(screen.getByText('EXP-2026-000001')).toBeInTheDocument());
    expect(screen.getByText('Total Posted')).toBeInTheDocument();
  });

  test('expense detail shows approve action for submitted', async () => {
    renderAt('/expenses/1', '/expenses/:id', ADMIN, {
      '/expenses/1': { data: { data: {
        id: 1, expense_number: 'EXP-2026-000001', property: { name: 'Sunset' }, vendor: null,
        category: 'repairs', description: 'Fix door', expense_date: '2026-09-20',
        amount: 15000, currency: 'PKR', status: 'submitted', submitted_by: 'Manager', approved_by: null,
      } } },
    }, <ExpenseDetailPage />);

    await waitFor(() => expect(screen.getByText('EXP-2026-000001')).toBeInTheDocument());
    expect(screen.getByText('Approve')).toBeInTheDocument();
    expect(screen.getByText('Reject')).toBeInTheDocument();
  });

  test('expense detail shows post action for approved', async () => {
    renderAt('/expenses/1', '/expenses/:id', ADMIN, {
      '/expenses/1': { data: { data: {
        id: 1, expense_number: 'EXP-2026-000001', property: { name: 'Sunset' }, vendor: null,
        category: 'repairs', description: 'Fix door', expense_date: '2026-09-20',
        amount: 15000, currency: 'PKR', status: 'approved', submitted_by: 'Manager', approved_by: 'Admin',
      } } },
    }, <ExpenseDetailPage />);

    await waitFor(() => expect(screen.getByText('Post Expense')).toBeInTheDocument());
  });

  test('tenant cannot access expenses (guard)', async () => {
    renderAt('/expenses', '/expenses', TENANT, {
      '/expenses': Promise.reject({ response: { status: 403, data: { message: 'Forbidden' } } }),
    }, <ExpensesPage />);

    await waitFor(() => expect(screen.getByText(/Forbidden/)).toBeInTheDocument());
  });
});
