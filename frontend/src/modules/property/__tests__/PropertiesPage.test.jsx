import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PropertiesPage from '../pages/PropertiesPage';
import { useAuth } from '../../../auth/AuthContext';
import { propertyApi } from '../services/propertyApi';

vi.mock('../../../auth/AuthContext', () => ({
  useAuth: vi.fn(),
}));

vi.mock('../services/propertyApi', async (importOriginal) => {
  const original = await importOriginal();
  return {
    ...original,
    propertyApi: {
      list: vi.fn(),
      get: vi.fn(),
      create: vi.fn(),
      update: vi.fn(),
      archive: vi.fn(),
      restore: vi.fn(),
    },
  };
});

const rows = [
  {
    id: 1,
    name: 'Gulshan Residency',
    city: 'Karachi',
    owner: { id: 7, name: 'Ahmed Khan' },
    buildings_count: 2,
    units_count: 8,
    status: 'active',
  },
  {
    id: 2,
    name: 'DHA Commercial Plaza',
    city: 'Lahore',
    owner: null,
    buildings_count: 1,
    units_count: 4,
    status: 'inactive',
  },
];

function renderPage() {
  return render(
    <MemoryRouter>
      <PropertiesPage />
    </MemoryRouter>
  );
}

describe('PropertiesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    useAuth.mockReturnValue({ hasPermission: () => true });
  });

  it('renders property rows from the API', async () => {
    propertyApi.list.mockResolvedValue({
      items: rows,
      meta: { current_page: 1, per_page: 15, total: 2 },
    });
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('Gulshan Residency')).toBeInTheDocument();
    });
    expect(screen.getByText('DHA Commercial Plaza')).toBeInTheDocument();
    expect(screen.getByText('Ahmed Khan')).toBeInTheDocument();
    expect(screen.getByText('2 properties in portfolio')).toBeInTheDocument();
  });

  it('shows an honest empty state when there are no properties', async () => {
    propertyApi.list.mockResolvedValue({
      items: [],
      meta: { current_page: 1, per_page: 15, total: 0 },
    });
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('No properties yet')).toBeInTheDocument();
    });
  });

  it('hides management actions without the manage permission', async () => {
    useAuth.mockReturnValue({
      hasPermission: (perm) => perm === 'properties.view',
    });
    propertyApi.list.mockResolvedValue({
      items: rows,
      meta: { current_page: 1, per_page: 15, total: 2 },
    });
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('Gulshan Residency')).toBeInTheDocument();
    });
    expect(screen.queryByText('+ New Property')).not.toBeInTheDocument();
    expect(screen.queryByText('Edit')).not.toBeInTheDocument();
    expect(screen.queryByText('Archive')).not.toBeInTheDocument();
  });

  it('shows an error with retry on API failure', async () => {
    propertyApi.list.mockRejectedValue({
      response: { data: { message: 'Server exploded' } },
    });
    renderPage();

    await waitFor(() => {
      expect(screen.getByText('Server exploded')).toBeInTheDocument();
    });
    expect(screen.getByText('Retry')).toBeInTheDocument();
  });
});
