import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { utilityBillApi, utilityErrorMessage, BILL_STATUSES } from '../services/utilityApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function UtilityBillsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    status: searchParams.get('status') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await utilityBillApi.list({
        per_page: 12,
        status: filters.status || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not load bills.'));
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
    { key: 'bill_number', label: 'Bill', render: (r) => <Link to={`/utilities/bills/${r.id}`} className="text-blue-400 hover:underline font-medium">{r.bill_number}</Link> },
    { key: 'meter', label: 'Meter', render: (r) => `${r.meter?.meter_number || '—'} (${r.meter?.utility_type || ''})` },
    { key: 'period', label: 'Period', render: (r) => `${r.period_start} → ${r.period_end}` },
    { key: 'consumption', label: 'Consumption', render: (r) => Number(r.consumption).toLocaleString() },
    { key: 'total', label: 'Total', render: (r) => <span className="font-medium">{fmt(r.total)}</span> },
    { key: 'tenant', label: 'Tenant', render: (r) => r.tenant?.name || <span className="text-slate-500">Owner absorbed</span> },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Utility Bills</h1>
      </div>

      <div className="flex flex-wrap gap-2">
        <select className="input" value={filters.status} onChange={(e) => setFilter({ status: e.target.value })}>
          {BILL_STATUSES.map((s) => <option key={s} value={s}>{s || 'All statuses'}</option>)}
        </select>
      </div>

      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <div className="text-slate-400">Loading bills…</div>
      ) : rows.length === 0 ? (
        <div className="empty-state">No utility bills yet.</div>
      ) : (
        <DataTable columns={columns} rows={rows} />
      )}
    </div>
  );
}
