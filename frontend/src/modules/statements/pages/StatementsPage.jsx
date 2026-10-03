import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { statementApi, statementErrorMessage, STATEMENT_STATUSES } from '../services/statementApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function StatementsPage() {
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
      const { items, meta } = await statementApi.list({
        per_page: 12,
        status: filters.status || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(statementErrorMessage(err, 'Could not load statements.'));
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
    { key: 'statement_number', label: 'Statement', render: (r) => <Link to={`/statements/${r.id}`} className="text-blue-400 hover:underline font-medium">{r.statement_number}</Link> },
    { key: 'owner', label: 'Owner', render: (r) => r.owner?.name || '—' },
    { key: 'period', label: 'Period', render: (r) => r.period ? `${r.period.start_date} → ${r.period.end_date}` : '—' },
    { key: 'gross_income', label: 'Income', render: (r) => fmt(r.gross_income) },
    { key: 'net_amount', label: 'Net', render: (r) => <span className="font-semibold">{fmt(r.net_amount)}</span> },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Owner Statements</h1>
        <div className="flex gap-2">
          <PermissionGuard permission="statements.generate">
            <Link to="/statements/generate" className="btn-primary">Generate Statement</Link>
          </PermissionGuard>
          <PermissionGuard permission="statements.review">
            <Link to="/statements/review" className="btn-secondary">Review Queue</Link>
          </PermissionGuard>
        </div>
      </div>

      <div className="flex flex-wrap gap-2">
        <select className="input" value={filters.status} onChange={(e) => setFilter({ status: e.target.value })}>
          {STATEMENT_STATUSES.map((s) => <option key={s} value={s}>{s || 'All statuses'}</option>)}
        </select>
        <Link to="/statements/reports" className="btn-secondary">Owner Reports</Link>
      </div>

      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <div className="text-slate-400">Loading statements…</div>
      ) : rows.length === 0 ? (
        <div className="empty-state">No owner statements yet.</div>
      ) : (
        <DataTable columns={columns} rows={rows} />
      )}
    </div>
  );
}
