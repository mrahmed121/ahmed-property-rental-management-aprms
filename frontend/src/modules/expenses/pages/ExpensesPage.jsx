import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { expenseApi, expenseErrorMessage, EXPENSE_CATEGORIES, EXPENSE_STATUSES } from '../services/expenseApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function ExpensesPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    category: searchParams.get('category') || '',
    status: searchParams.get('status') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const [{ items, meta }, sum] = await Promise.all([
        expenseApi.list({
          per_page: 12,
          search: filters.search || undefined,
          category: filters.category || undefined,
          status: filters.status || undefined,
          page: filters.page,
        }),
        expenseApi.summary().catch(() => null),
      ]);
      setRows(items);
      setMeta(meta);
      setSummary(sum);
    } catch (err) {
      setError(expenseErrorMessage(err, 'Could not load expenses.'));
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
    { key: 'expense_number', label: 'Expense', render: (r) => <Link to={`/expenses/${r.id}`} className="text-blue-400 hover:underline font-medium">{r.expense_number}</Link> },
    { key: 'property', label: 'Property', render: (r) => r.property?.name || '—' },
    { key: 'category', label: 'Category', render: (r) => <span className="capitalize">{r.category.replace('_', ' ')}</span> },
    { key: 'description', label: 'Description', render: (r) => <span className="text-slate-300">{r.description.slice(0, 60)}</span> },
    { key: 'expense_date', label: 'Date', render: (r) => r.expense_date },
    { key: 'amount', label: 'Amount', render: (r) => <span className="font-medium">{fmt(r.amount)}</span> },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-semibold">Property Expenses</h1>
        <PermissionGuard permission="expenses.create">
          <Link to="/expenses/new" className="btn-primary">New Expense</Link>
        </PermissionGuard>
      </div>

      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div className="card"><div className="text-slate-400 text-xs">Total Posted</div><div className="text-lg font-semibold">{fmt(summary.total_expenses)}</div></div>
          <div className="card"><div className="text-slate-400 text-xs">Pending Approval</div><div className="text-lg font-semibold">{summary.pending_approvals}</div></div>
          <div className="card"><div className="text-slate-400 text-xs">Drafts</div><div className="text-lg font-semibold">{summary.draft_count}</div></div>
          <div className="card"><div className="text-slate-400 text-xs">Posted Count</div><div className="text-lg font-semibold">{summary.posted_count}</div></div>
        </div>
      )}

      <div className="flex flex-wrap gap-2">
        <input className="input max-w-xs" placeholder="Search…" value={filters.search} onChange={(e) => setFilter({ search: e.target.value })} />
        <select className="input" value={filters.category} onChange={(e) => setFilter({ category: e.target.value })}>
          {EXPENSE_CATEGORIES.map((c) => <option key={c} value={c}>{c ? c.replace('_', ' ') : 'All categories'}</option>)}
        </select>
        <select className="input" value={filters.status} onChange={(e) => setFilter({ status: e.target.value })}>
          {EXPENSE_STATUSES.map((s) => <option key={s} value={s}>{s || 'All statuses'}</option>)}
        </select>
        <PermissionGuard permission="expenses.approve">
          <Link to="/expenses/approvals" className="btn-secondary">Approval Queue</Link>
        </PermissionGuard>
      </div>

      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <div className="text-slate-400">Loading expenses…</div>
      ) : rows.length === 0 ? (
        <div className="empty-state">No expenses yet.</div>
      ) : (
        <DataTable columns={columns} rows={rows} />
      )}
    </div>
  );
}
