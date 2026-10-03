import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { ticketApi, maintenanceErrorMessage } from '../services/maintenanceApi';

const STATUSES = ['', 'open', 'triaged', 'assigned', 'quoted', 'approval_pending', 'approved', 'in_progress', 'completed', 'verified', 'closed'];
const PRIORITIES = ['', 'low', 'normal', 'high', 'urgent'];

const PRIORITY_COLORS = {
  low: 'text-slate-400',
  normal: 'text-blue-300',
  high: 'text-yellow-300',
  urgent: 'text-red-300',
};

export default function TicketsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const filters = {
    search: searchParams.get('search') || '',
    status: searchParams.get('status') || '',
    priority: searchParams.get('priority') || '',
    breached: searchParams.get('breached') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await ticketApi.list({
        per_page: 12,
        search: filters.search || undefined,
        status: filters.status || undefined,
        priority: filters.priority || undefined,
        breached: filters.breached || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(maintenanceErrorMessage(err, 'Could not load tickets.'));
    } finally {
      setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams.toString()]);

  useEffect(() => { load(); }, [load]);

  const setFilter = (patch) => {
    const next = new URLSearchParams(searchParams);
    Object.entries(patch).forEach(([k, v]) => {
      if (v) next.set(k, v);
      else next.delete(k);
    });
    if (!patch.page) next.delete('page');
    setSearchParams(next);
  };

  const canReport = hasPermission('maintenance.report');

  return (
    <PermissionGuard permission="maintenance.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Maintenance Tickets</h2>
            <p className="mt-1 text-sm text-slate-400">From report to verified fix, with SLA tracking.</p>
          </div>
          <div className="flex gap-2">
            <Link to="/maintenance/dashboard" className="aprms-btn-ghost">Dashboard</Link>
            {canReport && <Link to="/maintenance/tickets/new" className="aprms-btn-gold">+ New Ticket</Link>}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
          <input
            className="aprms-input"
            placeholder="Search ticket no., description…"
            value={filters.search}
            onChange={(e) => setFilter({ search: e.target.value })}
          />
          <select className="aprms-input" value={filters.status} onChange={(e) => setFilter({ status: e.target.value })}>
            {STATUSES.map((s) => <option key={s} value={s}>{s ? s.replace(/_/g, ' ') : 'All statuses'}</option>)}
          </select>
          <select className="aprms-input" value={filters.priority} onChange={(e) => setFilter({ priority: e.target.value })}>
            {PRIORITIES.map((p) => <option key={p} value={p}>{p || 'All priorities'}</option>)}
          </select>
          <label className="flex items-center gap-2 text-sm text-slate-300">
            <input
              type="checkbox"
              checked={!!filters.breached}
              onChange={(e) => setFilter({ breached: e.target.checked ? '1' : '' })}
              className="h-4 w-4 accent-yellow-600"
            />
            SLA breached only
          </label>
        </div>

        <DataTable
          columns={[
            {
              key: 'ticket_number',
              label: 'Ticket',
              render: (r) => (
                <Link to={`/maintenance/tickets/${r.id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.ticket_number}
                  {r.breached && <span className="ml-2 text-xs text-red-400">SLA breached</span>}
                </Link>
              ),
            },
            { key: 'property', label: 'Property', render: (r) => (
              <span className="text-slate-400">{r.property?.name || '—'}{r.unit ? ` · ${r.unit.unit_number}` : ''}</span>
            ) },
            { key: 'category', label: 'Category', render: (r) => <span className="text-slate-400">{r.category}</span> },
            { key: 'priority', label: 'Priority', render: (r) => (
              <span className={`font-medium capitalize ${PRIORITY_COLORS[r.priority]}`}>{r.priority}</span>
            ) },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            { key: 'assigned', label: 'Assigned', render: (r) => <span className="text-slate-400">{r.assigned_to?.name || '—'}</span> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                <Link to={`/maintenance/tickets/${r.id}`} className="aprms-btn-ghost !px-3 !py-1.5 !text-xs">Open</Link>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No tickets"
          emptyHint="Report a maintenance issue to start the workflow: triage → assign → quote → approve → work → verify → close."
          emptyAction={canReport ? <Link to="/maintenance/tickets/new" className="aprms-btn-gold">+ New Ticket</Link> : undefined}
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
