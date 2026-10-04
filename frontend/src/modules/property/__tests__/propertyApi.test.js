import { beforeEach, describe, expect, it, vi } from 'vitest';
import client from '../../../api/client';
import {
  propertiesApi,
  buildingsApi,
  unitsApi,
  dashboardApi,
  formatPKR,
} from '../services/propertyApi';

vi.mock('../../../api/client', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('propertyApi URL construction', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    client.get.mockResolvedValue({ data: { data: [], meta: {} } });
    client.post.mockResolvedValue({ data: { data: { id: 1 } } });
    client.put.mockResolvedValue({ data: { data: { id: 1 } } });
    client.delete.mockResolvedValue({ data: { data: { id: 1 } } });
  });

  it('lists properties with filters as query params', async () => {
    await propertiesApi.list({ search: 'gulshan', status: 'active', page: 2 });
    expect(client.get).toHaveBeenCalledWith('/properties', {
      params: { search: 'gulshan', status: 'active', page: 2 },
    });
  });

  it('omits empty filter values from params', async () => {
    await propertiesApi.list({ search: '', status: null, page: undefined });
    expect(client.get).toHaveBeenCalledWith('/properties', { params: {} });
  });

  it('builds property CRUD URLs', async () => {
    await propertiesApi.get(7);
    expect(client.get).toHaveBeenCalledWith('/properties/7');
    await propertiesApi.create({ name: 'X' });
    expect(client.post).toHaveBeenCalledWith('/properties', { name: 'X' });
    await propertiesApi.update(7, { name: 'Y' });
    expect(client.put).toHaveBeenCalledWith('/properties/7', { name: 'Y' });
    await propertiesApi.archive(7);
    expect(client.delete).toHaveBeenCalledWith('/properties/7');
    await propertiesApi.restore(7);
    expect(client.post).toHaveBeenCalledWith('/properties/7/restore');
  });

  it('builds building URLs with property filter', async () => {
    await buildingsApi.list({ property_id: 3 });
    expect(client.get).toHaveBeenCalledWith('/buildings', {
      params: { property_id: 3 },
    });
    await buildingsApi.get(5);
    expect(client.get).toHaveBeenCalledWith('/buildings/5');
  });

  it('builds unit URLs with building and status filters', async () => {
    await unitsApi.list({ building_id: 2, status: 'vacant' });
    expect(client.get).toHaveBeenCalledWith('/units', {
      params: { building_id: 2, status: 'vacant' },
    });
    await unitsApi.create({ building_id: 2, unit_number: 'A-101', unit_type: 'apartment' });
    expect(client.post).toHaveBeenCalledWith('/units', {
      building_id: 2,
      unit_number: 'A-101',
      unit_type: 'apartment',
    });
  });

  it('fetches dashboard stats', async () => {
    client.get.mockResolvedValue({ data: { data: { total_properties: 5 } } });
    const stats = await dashboardApi.stats();
    expect(client.get).toHaveBeenCalledWith('/dashboard/stats');
    expect(stats.total_properties).toBe(5);
  });

  it('unwraps paginated lists into { items, meta }', async () => {
    client.get.mockResolvedValue({
      data: {
        data: [{ id: 1 }],
        meta: { current_page: 1, per_page: 15, total: 1 },
      },
    });
    const { items, meta } = await propertiesApi.list({});
    expect(items).toHaveLength(1);
    expect(meta.total).toBe(1);
  });
});

describe('formatPKR', () => {
  it('formats numbers as PKR', () => {
    expect(formatPKR(45000)).toBe('PKR 45,000');
    expect(formatPKR('1250000')).toBe('PKR 1,250,000');
  });

  it('returns em dash for missing values', () => {
    expect(formatPKR(null)).toBe('—');
    expect(formatPKR(undefined)).toBe('—');
    expect(formatPKR('')).toBe('—');
  });
});
