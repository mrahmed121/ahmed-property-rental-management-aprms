import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { vi } from 'vitest';
import TicketsPage from '../modules/maintenance/pages/TicketsPage';
import MaintenanceDashboardPage from '../modules/maintenance/pages/MaintenanceDashboardPage';
import { AuthProvider } from '../context/AuthContext';
import api from '../services/api';

vi.mock('../services/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
  getToken: () => 'test-token',
  setToken: vi.fn(),
}));

const ADMIN = {
  id: 1, name: 'Admin', email: 'admin@ahmedestates.local',
  permissions: ['maintenance.view', 'maintenance.report', 'maintenance.triage', 'maintenance.work', 'maintenance.approve'],
};

function mockApi(overrides = {}) {
  api.get.mockImplementation((url) => {
    if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
    if (overrides[url]) return Promise.resolve(overrides[url]);
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

function renderAt(path, route, element, overrides) {
  mockApi(overrides);
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

const TICKETS_PAGE = {
  data: {
    data: [
      {
        id: 1,
        ticket_number: 'MT-2026-000001',
        property: { id: 1, name: 'Gulshan Heights' },
        unit: { id: 1, unit_number: 'A-101' },
        category: 'plumbing',
        priority: 'urgent',
        status: 'open',
        assigned_to: null,
        breached: true,
      },
    ],
    meta: { current_page: 1, per_page: 12, total: 1 },
  },
};

const EMPTY_PAGE = {
  data: { data: [], meta: { current_page: 1, per_page: 12, total: 0 } },
};

const DASHBOARD_METRICS = {
  data: {
    data: {
      open_tickets: 3,
      urgent_tickets: 1,
      sla_breached: 1,
      sla_due_soon: 0,
      pending_approvals: 1,
      in_progress: 0,
      assigned: 1,
      completed_this_month: 1,
      approved_spend: 2300,
      by_priority: { low: 1, normal: 1, high: 0, urgent: 1 },
      by_status: { open: 1, assigned: 1, approval_pending: 1 },
    },
  },
};

describe('TicketsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('maintenance ticket list renders', async () => {
    renderAt('/maintenance/tickets', '/maintenance/tickets', <TicketsPage />, {
      '/maintenance/tickets': TICKETS_PAGE,
    });

    await waitFor(() => {
      expect(screen.getByText('MT-2026-000001')).toBeInTheDocument();
    });
    expect(screen.getByText('SLA breached')).toBeInTheDocument();
  });

  test('empty state shows workflow guidance', async () => {
    renderAt('/maintenance/tickets', '/maintenance/tickets', <TicketsPage />, {
      '/maintenance/tickets': EMPTY_PAGE,
    });

    await waitFor(() => {
      expect(screen.getByText('No tickets')).toBeInTheDocument();
    });
  });
});

describe('MaintenanceDashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  test('dashboard renders real metrics', async () => {
    renderAt('/maintenance', '/maintenance', <MaintenanceDashboardPage />, {
      '/maintenance/dashboard': DASHBOARD_METRICS,
    });

    await waitFor(() => {
      expect(screen.getByText('Maintenance Overview')).toBeInTheDocument();
    });
    expect(screen.getByText('Open tickets')).toBeInTheDocument();
    expect(screen.getByText('SLA breached')).toBeInTheDocument();
  });
});
