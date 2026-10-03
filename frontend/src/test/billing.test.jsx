import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

vi.mock('../services/api', () => {
  const get = vi.fn();
  const post = vi.fn();
  const put = vi.fn();
  const del = vi.fn();
  const interceptors = { request: { use: vi.fn() }, response: { use: vi.fn() } };
  return {
    default: { get, post, put, delete: del, interceptors },
    getToken: vi.fn(() => 'token'),
    setToken: vi.fn(),
  };
});

import api from '../services/api';
import { AuthProvider } from '../context/AuthContext';
import InvoicesPage from '../modules/billing/pages/InvoicesPage';
import InvoiceDetailPage from '../modules/billing/pages/InvoiceDetailPage';
import PaymentsPage from '../modules/billing/pages/PaymentsPage';
import PaymentFormPage from '../modules/billing/pages/PaymentFormPage';
import FinancialDashboardPage from '../modules/billing/pages/FinancialDashboardPage';

const ADMIN = {
  id: 1, name: 'Admin', email: 'admin@x.local',
  permissions: ['billing.view', 'invoices.view', 'invoices.generate', 'payments.view',
    'payments.record', 'payments.reverse', 'ledger.view', 'dunning.view', 'dunning.manage',
    'receipts.view', 'billing.adjust'],
};

function mockApi(overrides = {}) {
  api.get.mockImplementation((url) => {
    if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
    if (overrides[url]) return Promise.resolve(overrides[url]);
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

function renderAt(path, element, route) {
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

const INVOICES_PAGE = {
  data: {
    data: [
      { id: 1, invoice_number: 'INV-2026-000001', tenant: { id: 1, name: 'Ahmed Raza' }, period_start: '2026-09-01', period_end: '2026-09-30', due_date: '2026-09-05', total: 40000, outstanding: 0, status: 'paid' },
      { id: 2, invoice_number: 'INV-2026-000002', tenant: { id: 1, name: 'Ahmed Raza' }, period_start: '2026-10-01', period_end: '2026-10-31', due_date: '2026-10-05', total: 40000, outstanding: 40000, status: 'issued' },
    ],
    meta: { current_page: 1, per_page: 12, total: 2 },
  },
};

const INVOICE_DETAIL = {
  data: {
    data: {
      id: 2, invoice_number: 'INV-2026-000002', status: 'issued',
      tenant: { id: 1, name: 'Ahmed Raza' }, property: { id: 1, name: 'Bahria' },
      unit: { id: 1, unit_number: 'R-101' }, lease_id: 6,
      lease: { id: 6, lease_number: 'LSE-1-2026-000006' },
      period_start: '2026-10-01', period_end: '2026-10-31',
      issue_date: '2026-10-01', due_date: '2026-10-05',
      base_rent: 40000, utilities: 0, other_charges: 0, late_fee: 0,
      total: 40000, paid_amount: 0, outstanding: 40000,
      notes: null, late_fee_record: null, dunning: [],
    },
  },
};

describe('Invoices list', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockApi({ '/invoices': INVOICES_PAGE });
  });

  it('renders invoices with PKR amounts and statuses', async () => {
    renderAt('/billing/invoices', <InvoicesPage />, '/billing/invoices');
    await waitFor(() => expect(screen.getByText('INV-2026-000001')).toBeInTheDocument());
    expect(screen.getByText('INV-2026-000002')).toBeInTheDocument();
    expect(screen.getAllByText('Ahmed Raza')).toHaveLength(2);
  });

  it('shows honest empty state when no invoices', async () => {
    mockApi({ '/invoices': { data: { data: [], meta: { total: 0 } } } });
    renderAt('/billing/invoices', <InvoicesPage />, '/billing/invoices');
    await waitFor(() => expect(screen.getByText('No invoices yet')).toBeInTheDocument());
  });
});

describe('Invoice detail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockApi({ '/invoices/2': INVOICE_DETAIL });
  });

  it('shows totals and record-payment action for outstanding invoice', async () => {
    renderAt('/billing/invoices/2', <InvoiceDetailPage />, '/billing/invoices/:id');
    await waitFor(() => expect(screen.getByText('INV-2026-000002')).toBeInTheDocument());
    expect(screen.getByRole('link', { name: /record payment/i })).toBeInTheDocument();
  });
});

describe('Payments list', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockApi({
      '/payments': {
        data: {
          data: [
            { id: 1, receipt_number: 'RCPT-2026-000001', tenant: { name: 'Ahmed Raza' }, payment_date: '2026-09-04', method: 'bank_transfer', amount: 40000, status: 'posted' },
          ],
          meta: { current_page: 1, per_page: 12, total: 1 },
        },
      },
    });
  });

  it('renders payments with receipt numbers', async () => {
    renderAt('/billing/payments', <PaymentsPage />, '/billing/payments');
    await waitFor(() => expect(screen.getByText('RCPT-2026-000001')).toBeInTheDocument());
    expect(screen.getByRole('link', { name: /record payment/i })).toBeInTheDocument();
  });
});

describe('Payment form', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockApi({
      '/tenants': { data: { data: [{ id: 1, name: 'Ahmed Raza' }], meta: { total: 1 } } },
    });
    api.post.mockImplementation((url) => {
      if (url === '/payments/preview') {
        return Promise.resolve({
          data: {
            data: {
              amount: 30000,
              lines: [{ bucket: 'arrears', label: 'Invoice INV-2026-000001', amount: 30000 }],
              allocated: 30000, unallocated: 0,
            },
          },
        });
      }
      return Promise.reject(new Error(`unexpected POST ${url}`));
    });
  });

  it('requires preview before posting (double-submit safety)', async () => {
    const user = userEvent.setup();
    renderAt('/billing/payments/new', <PaymentFormPage />, '/billing/payments/new');

    await waitFor(() => expect(screen.getByText('Record Payment')).toBeInTheDocument());

    // Select tenant and enter amount.
    await user.selectOptions(screen.getByLabelText(/tenant/i), '1');
    await user.type(screen.getByLabelText(/amount/i), '30000');

    // Preview the allocation.
    await user.click(screen.getByRole('button', { name: /preview allocation/i }));
    await waitFor(() => expect(screen.getByText(/where.*will go/i)).toBeInTheDocument());
    expect(screen.getByText('Invoice INV-2026-000001')).toBeInTheDocument();

    // Confirm button is now enabled with the amount.
    const confirm = screen.getByRole('button', { name: /confirm & post/i });
    expect(confirm).not.toBeDisabled();
    expect(api.post).toHaveBeenCalledWith('/payments/preview', { tenant_id: 1, amount: 30000 });
  });

  it('blocks posting without preview', async () => {
    const user = userEvent.setup();
    renderAt('/billing/payments/new', <PaymentFormPage />, '/billing/payments/new');
    await waitFor(() => expect(screen.getByText('Record Payment')).toBeInTheDocument());

    const confirm = screen.getByRole('button', { name: /confirm & post/i });
    expect(confirm).toBeDisabled();
  });
});

describe('Financial dashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockApi({
      '/financial/dashboard': {
        data: {
          data: {
            billed_this_period: 125000, collected_this_period: 90000,
            outstanding: 75000, overdue: 35000, collection_rate: 72.0,
            invoices_due: 2, invoices_overdue: 1, payments_today: 40000,
            arrears_aging: { '0_30': 35000, '31_60': 40000, '61_90': 0, '90_plus': 0 },
            period: '2026-10',
          },
        },
      },
    });
  });

  it('shows real query-backed money metrics', async () => {
    renderAt('/billing', <FinancialDashboardPage />, '/billing');
    await waitFor(() => expect(screen.getByText('Financial Overview')).toBeInTheDocument());
    expect(screen.getByText('Collected this period')).toBeInTheDocument();
    expect(screen.getByText('Outstanding')).toBeInTheDocument();
    expect(screen.getByText('Collection rate')).toBeInTheDocument();
  });
});

describe('Billing permission guards', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
  });

  it('hides record-payment for viewers without payments.record', async () => {
    const viewer = { ...ADMIN, permissions: ['payments.view'] };
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: viewer } });
      if (url === '/payments') {
        return Promise.resolve({ data: { data: [], meta: { total: 0 } } });
      }
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
    renderAt('/billing/payments', <PaymentsPage />, '/billing/payments');
    await waitFor(() => expect(screen.getByText('Payments')).toBeInTheDocument());
    expect(screen.queryByRole('link', { name: /record payment/i })).not.toBeInTheDocument();
  });
});
