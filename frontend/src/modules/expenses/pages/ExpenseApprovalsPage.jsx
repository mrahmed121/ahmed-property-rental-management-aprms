import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import DataTable from '../../property/components/DataTable';
import { expenseApi, expenseErrorMessage } from '../services/expenseApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function ExpenseApprovalsPage() {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items } = await expenseApi.list({ status: 'submitted', per_page: 50 });
      setRows(items);
    } catch (err) {
      setError(expenseErrorMessage(err, 'Could not load approval queue.'));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  const columns = [
    { key: 'expense_number', label: 'Expense', render: (r) => <Link to={`/expenses/${r.id}`} className="text-blue-400 hover:underline font-medium">{r.expense_number}</Link> },
    { key: 'property', label: 'Property', render: (r) => r.property?.name || '—' },
    { key: 'category', label: 'Category', render: (r) => <span className="capitalize">{r.category.replace('_', ' ')}</span> },
    { key: 'amount', label: 'Amount', render: (r) => <span className="font-medium">{fmt(r.amount)}</span> },
    { key: 'submitted_by', label: 'Submitted By', render: (r) => r.submitted_by || '—' },
  ];

  return (
    <div className="space-y-4">
      <h1 className="text-xl font-semibold">Expense Approval Queue</h1>
      {error && <div className="alert-error">{error}</div>}
      {loading ? (
        <div className="text-slate-400">Loading…</div>
      ) : rows.length === 0 ? (
        <div className="empty-state">No expenses awaiting approval.</div>
      ) : (
        <DataTable columns={columns} rows={rows} />
      )}
    </div>
  );
}
