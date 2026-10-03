import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { statementApi, statementErrorMessage } from '../services/statementApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function StatementReviewPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items } = await statementApi.list({ status: 'review', per_page: 50 });
      setRows(items);
    } catch (err) {
      setError(statementErrorMessage(err, 'Could not load review queue.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const columns = [
    { key: 'statement_number', label: 'Statement', render: (r) => <Link to={`/statements/${r.id}`} className="text-blue-400 hover:underline font-medium">{r.statement_number}</Link> },
    { key: 'owner', label: 'Owner', render: (r) => r.owner?.name || '—' },
    { key: 'period', label: 'Period', render: (r) => r.period ? `${r.period.start_date} → ${r.period.end_date}` : '—' },
    { key: 'net_amount', label: 'Net', render: (r) => <span className="font-semibold">{fmt(r.net_amount)}</span> },
    { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Statement Review Queue</h1>
      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <div className="text-slate-400">Loading…</div>
      ) : rows.length === 0 ? (
        <div className="empty-state">No statements awaiting review.</div>
      ) : (
        <DataTable columns={columns} rows={rows} />
      )}
    </div>
  );
}
