import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { meterApi, utilityErrorMessage, UTILITY_TYPES, METER_STATUSES } from '../services/utilityApi';

export default function MetersPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    utility_type: searchParams.get('utility_type') || '',
    status: searchParams.get('status') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await meterApi.list({
        per_page: 12,
        search: filters.search || undefined,
        utility_type: filters.utility_type || undefined,
        status: filters.status || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not load meters.'));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams.toString()]);

  useEffect(() => { load(); }, [load]);

  const setFilter = (patch) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(patch).forEach(([k, v]) => {
      if (v) next.set(k, v); else next.delete(k);
    });
    next.set('page', '1');
    setSearchParams(next);
  };

  const columns = [
    { key: 'meter_number', label: 'Meter', render: (r) => <Link to={`/utilities/meters/${r.id}`} className="text-blue-400 hover:underline font-medium">{r.meter_number}</Link> },
    { key: 'utility_type', label: 'Type', render: (r) => <span className="capitalize">{r.utility_type}</span> },
    { key: 'property', label: 'Property', render: (r) => r.property?.name || '—' },
    { key: 'unit', label: 'Unit', render: (r) => r.unit?.unit_number || '—' },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Utility Meters</h1>
        <PermissionGuard permission="utilities.manage">
          <Link to="/utilities/meters/new" className="btn-primary">Register Meter</Link>
        </PermissionGuard>
      </div>

      <div className="flex flex-wrap gap-2">
        <input
          className="input max-w-xs"
          placeholder="Search meter number…"
          value={filters.search}
          onChange={(e) => setFilter({ search: e.target.value })}
        />
        <select className="input" value={filters.utility_type} onChange={(e) => setFilter({ utility_type: e.target.value })}>
          {UTILITY_TYPES.map((t) => <option key={t} value={t}>{t || 'All types'}</option>)}
        </select>
        <select className="input" value={filters.status} onChange={(e) => setFilter({ status: e.target.value })}>
          {METER_STATUSES.map((s) => <option key={s} value={s}>{s || 'All statuses'}</option>)}
        </select>
      </div>

      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <div className="text-slate-400">Loading meters…</div>
      ) : rows.length === 0 ? (
        <div className="empty-state">No meters registered yet.</div>
      ) : (
        <DataTable columns={columns} rows={rows} />
      )}
    </div>
  );
}
