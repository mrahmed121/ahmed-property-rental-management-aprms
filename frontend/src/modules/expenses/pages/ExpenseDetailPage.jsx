import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import StatusBadge from '../../property/components/StatusBadge';
import { expenseApi, expenseErrorMessage } from '../services/expenseApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

const NEXT_ACTIONS = {
  draft: [{ to: 'submitted', label: 'Submit for Approval', perm: 'expenses.create' }],
  submitted: [
    { to: 'approved', label: 'Approve', perm: 'expenses.approve' },
    { to: 'rejected', label: 'Reject', perm: 'expenses.approve' },
  ],
  approved: [{ to: 'posted', label: 'Post Expense', perm: 'expenses.post' }],
  rejected: [{ to: 'draft', label: 'Return to Draft', perm: 'expenses.create' }],
};

export default function ExpenseDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const [expense, setExpense] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      setExpense(await expenseApi.get(id));
    } catch (err) {
      setError(expenseErrorMessage(err, 'Could not load expense.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const transition = async (to) => {
    if (!window.confirm(`Move expense to "${to}"?`)) return;
    setBusy(true);
    setError('');
    try {
      await expenseApi.transition(id, to);
      await load();
    } catch (err) {
      setError(expenseErrorMessage(err, 'Transition failed.'));
    } finally {
      setBusy(false);
    }
  };

  const reverse = async () => {
    const reason = window.prompt('Reason for reversal (required):');
    if (!reason || reason.trim().length < 5) return;
    setBusy(true);
    try {
      await expenseApi.reverse(id, reason.trim());
      await load();
    } catch (err) {
      setError(expenseErrorMessage(err, 'Could not reverse expense.'));
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <div className="text-slate-400">Loading expense…</div>;
  if (error && !expense) return <div className="alert-error">{error}</div>;
  if (!expense) return null;

  const actions = NEXT_ACTIONS[expense.status] || [];

  return (
    <div className="space-y-6 max-w-3xl">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">{expense.expense_number}</h1>
          <p className="text-slate-400 text-sm capitalize">{expense.category.replace('_', ' ')} · {expense.property?.name} · {expense.expense_date}</p>
        </div>
        <StatusBadge status={expense.status} />
      </div>

      {error && <div className="alert-error">{error}</div>}

      <div className="card space-y-2 text-sm">
        <p className="text-slate-300">{expense.description}</p>
        <div className="flex justify-between border-t border-slate-800 pt-2"><span className="text-slate-400">Amount</span><span className="font-semibold text-base">{fmt(expense.amount)}</span></div>
        {expense.vendor && <div className="flex justify-between"><span className="text-slate-400">Vendor</span><span>{expense.vendor.name}</span></div>}
        {expense.building && <div className="flex justify-between"><span className="text-slate-400">Building</span><span>{expense.building.name}</span></div>}
        {expense.unit && <div className="flex justify-between"><span className="text-slate-400">Unit</span><span>{expense.unit.unit_number}</span></div>}
        {expense.submitted_by && <div className="flex justify-between"><span className="text-slate-400">Submitted by</span><span>{expense.submitted_by}</span></div>}
        {expense.approved_by && <div className="flex justify-between"><span className="text-slate-400">Approved by</span><span>{expense.approved_by} · {expense.approved_at}</span></div>}
        {expense.posted_at && <div className="flex justify-between"><span className="text-slate-400">Posted at</span><span>{expense.posted_at}</span></div>}
        {expense.notes && <div className="text-slate-400 whitespace-pre-wrap border-t border-slate-800 pt-2">{expense.notes}</div>}
      </div>

      <div className="flex flex-wrap gap-2">
        {actions.map((a) => (
          <PermissionGuard key={a.to} permission={a.perm}>
            <button className={a.to === 'rejected' ? 'btn-danger' : 'btn-primary'} onClick={() => transition(a.to)} disabled={busy}>
              {busy ? 'Working…' : a.label}
            </button>
          </PermissionGuard>
        ))}
        <PermissionGuard permission="expenses.reverse">
          {expense.status === 'posted' && (
            <button className="btn-danger" onClick={reverse} disabled={busy}>{busy ? 'Working…' : 'Reverse'}</button>
          )}
        </PermissionGuard>
      </div>
    </div>
  );
}
