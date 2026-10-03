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
    getToken: vi.fn(() => 'fake-token'),
    setToken: vi.fn(),
  };
});

import api, { getToken } from '../services/api';
import { AuthProvider } from '../context/AuthContext';
import PropertiesPage from '../modules/property/pages/PropertiesPage';
import PropertyFormPage from '../modules/property/pages/PropertyFormPage';
import PropertyDetailPage from '../modules/property/pages/PropertyDetailPage';
import Dashboard from '../pages/Dashboard';

const ADMIN = {
  id: 1, name: 'Agency Admin', email: 'admin@ahmedestates.local',
  roles: ['agency-admin'],
  permissions: ['dashboard.view', 'properties.view', 'properties.manage', 'buildings.view', 'buildings.manage', 'units.view', 'units.manage', 'documents.view', 'documents.manage'],
  agency: { id: 1, name: 'Ahmed Estates' },
};

const VIEWER = {
  ...ADMIN,
  name: 'Auditor Demo',
  roles: ['auditor'],
  permissions: ['dashboard.view', 'properties.view', 'buildings.view', 'units.view', 'documents.view'],
};

function mockMe(user = ADMIN) {
  api.get.mockImplementation((url) => {
    if (url === '/me') return Promise.resolve({ data: { data: user } });
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
}

const PROPS_PAGE = {
  data: {
    data: [
      { id: 1, name: 'Gulshan Residency', property_type: 'residential', city: 'Karachi', status: 'active', buildings_count: 2, units_count: 8 },
      { id: 2, name: 'DHA Trade Tower', property_type: 'commercial', city: 'Karachi', status: 'active', buildings_count: 1, units_count: 6 },
    ],
    meta: { current_page: 1, per_page: 12, total: 2 },
  },
};

function renderAt(path, element) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Routes>
          <Route path="/properties" element={element} />
          <Route path="/properties/new" element={<PropertyFormPage />} />
          <Route path="/properties/:id" element={<PropertyDetailPage />} />
          <Route path="/properties/:id/edit" element={<PropertyFormPage />} />
          <Route path="/" element={<Dashboard />} />
        </Routes>
      </AuthProvider>
    </MemoryRouter>
  );
}

describe('Properties list', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockMe();
  });

  it('renders properties from the API', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      if (url === '/properties') return Promise.resolve(PROPS_PAGE);
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    renderAt('/properties', <PropertiesPage />);

    await waitFor(() => {
      expect(screen.getByText('Gulshan Residency')).toBeInTheDocument();
      expect(screen.getByText('DHA Trade Tower')).toBeInTheDocument();
    });
    expect(screen.getByText('2 buildings · 8 units')).toBeInTheDocument();
  });

  it('shows an honest empty state when there are no properties', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      if (url === '/properties')
        return Promise.resolve({ data: { data: [], meta: { current_page: 1, per_page: 12, total: 0 } } });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    renderAt('/properties', <PropertiesPage />);

    await waitFor(() => {
      expect(screen.getByText('No properties yet')).toBeInTheDocument();
    });
  });

  it('hides management actions from read-only roles', async () => {
    mockMe(VIEWER);
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: VIEWER } });
      if (url === '/properties') return Promise.resolve(PROPS_PAGE);
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    renderAt('/properties', <PropertiesPage />);

    await waitFor(() => {
      expect(screen.getByText('Gulshan Residency')).toBeInTheDocument();
    });
    expect(screen.queryByText('+ New Property')).not.toBeInTheDocument();
    expect(screen.queryByText('Archive')).not.toBeInTheDocument();
  });

  it('shows a forbidden notice without properties.view', async () => {
    const noAccess = { ...ADMIN, permissions: ['dashboard.view'] };
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: noAccess } });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    renderAt('/properties', <PropertiesPage />);

    await waitFor(() => {
      expect(screen.getByText('Access restricted')).toBeInTheDocument();
    });
  });
});

describe('Create property', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockMe();
  });

  it('submits the form and navigates to the new property', async () => {
    const user = userEvent.setup();
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });
    api.post.mockResolvedValueOnce({
      data: { data: { id: 99, name: 'Test Plaza', property_type: 'commercial', city: 'Karachi', status: 'active', buildings: [] } },
    });
    // Detail page load after navigation.
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      if (url === '/properties/99')
        return Promise.resolve({ data: { data: { id: 99, name: 'Test Plaza', property_type: 'commercial', address: '1 Test Rd', city: 'Karachi', status: 'active', buildings: [] } } });
      if (url === '/buildings') return Promise.resolve({ data: { data: [], meta: {} } });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    renderAt('/properties/new', <PropertyFormPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/property name/i)).toBeInTheDocument();
    });

    await user.type(screen.getByLabelText(/property name/i), 'Test Plaza');
    await user.type(screen.getByLabelText(/street address/i), '1 Test Rd');
    await user.type(screen.getByLabelText(/^city/i), 'Karachi');
    await user.click(screen.getByRole('button', { name: /create property/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/properties',
        expect.objectContaining({ name: 'Test Plaza', city: 'Karachi' })
      );
    });
    await waitFor(() => {
      expect(screen.getByText('Test Plaza')).toBeInTheDocument();
    });
  });

  it('shows inline validation errors from the API', async () => {
    const user = userEvent.setup();
    api.post.mockRejectedValueOnce({
      response: { data: { message: 'Validation failed.', errors: { name: ['The name field is required.'] } } },
    });

    renderAt('/properties/new', <PropertyFormPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /create property/i })).toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: /create property/i }));

    await waitFor(() => {
      expect(screen.getByText('The name field is required.')).toBeInTheDocument();
    });
  });
});

describe('Property detail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockMe();
  });

  const DETAIL = {
    data: {
      data: {
        id: 1, name: 'Gulshan Residency', property_type: 'residential',
        address: 'Plot 12-C', city: 'Karachi', postal_code: '75300',
        status: 'active', description: 'Demo complex.',
        owner: { id: 5, name: 'Owner Demo' },
        buildings: [{ id: 1, name: 'Block A', floors: 5, status: 'active' }],
      },
    },
  };

  it('renders the detail page with tab navigation', async () => {
    const user = userEvent.setup();
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      if (url === '/properties/1') return Promise.resolve(DETAIL);
      if (url === '/buildings') return Promise.resolve({ data: { data: [{ id: 1, name: 'Block A', floors: 5, status: 'active', units_count: 4 }], meta: {} } });
      if (url === '/units') return Promise.resolve({ data: { data: [], meta: { current_page: 1, per_page: 12, total: 0 } } });
      if (url === '/documents') return Promise.resolve({ data: { data: [], meta: {} } });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    renderAt('/properties/1', <PropertyDetailPage />);

    await waitFor(() => {
      expect(screen.getByText('Gulshan Residency')).toBeInTheDocument();
    });
    expect(screen.getByText('Owner Demo')).toBeInTheDocument();

    // Switch to the Units tab — empty state, no fake rows.
    await user.click(screen.getByRole('button', { name: 'Units' }));
    await waitFor(() => {
      expect(screen.getByText('No units yet')).toBeInTheDocument();
    });
  });
});

describe('Dashboard stats', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    mockMe();
  });

  it('renders real query-backed numbers', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      if (url === '/dashboard/stats')
        return Promise.resolve({
          data: {
            data: {
              total_properties: 3, total_buildings: 6, total_units: 20,
              vacant_units: 7, occupied_units: 9,
              units_by_status: { vacant: 7, occupied: 9, reserved: 2, maintenance: 2 },
            },
          },
        });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    render(
      <MemoryRouter initialEntries={['/']}>
        <AuthProvider>
          <Dashboard />
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('3')).toBeInTheDocument();
      expect(screen.getByText('20')).toBeInTheDocument();
    });
    expect(screen.queryByText('No data yet')).not.toBeInTheDocument();
  });

  it('shows an honest empty state with zero properties', async () => {
    api.get.mockImplementation((url) => {
      if (url === '/me') return Promise.resolve({ data: { data: ADMIN } });
      if (url === '/dashboard/stats')
        return Promise.resolve({
          data: { data: { total_properties: 0, total_buildings: 0, total_units: 0, vacant_units: 0, occupied_units: 0, units_by_status: {} } },
        });
      return Promise.reject(new Error(`unexpected GET ${url}`));
    });

    render(
      <MemoryRouter initialEntries={['/']}>
        <AuthProvider>
          <Dashboard />
        </AuthProvider>
      </MemoryRouter>
    );

    await waitFor(() => {
      expect(screen.getByText('No property data yet')).toBeInTheDocument();
    });
  });
});
