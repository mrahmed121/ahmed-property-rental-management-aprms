import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import StatusBadge from '../../property/components/StatusBadge';
import { statementApi, statementErrorMessage } from '../services/statementApi';
import api from '../../../services/api';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

const NEXT_ACTIONS = {
  draft: [{ to: 'review', label: 'Submit for Review', perm: 'statements.review' }],
  review: [
    { to: 'approved', label: 'Approve', perm: 'statements.approve' },
    { to: 'draft', label: 'Return to Draft', perm: 'statements.review' },
  ],
  approved: [{ to: 'finalized', label: 'Finalize', perm: 'statements.finalize' }],
};

const TYPE_LABELS = {
  income: 'Income', management_fee: 'Management Fee', expense: 'Expense',
  maintenance: 'Maintenance', utility: 'Utility', adjustment: 'Adjustment',
};

export default function StatementDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [statement, setStatement] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [adjust, setAdjust] = useState({ amount: '', reason: '' });

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      setStatement(await statementApi.get(id));
    } catch (err) {
      setError(statementErrorMessage(err, 'Could not load statement.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const transition = async (to) => {
    if (!window.confirm(`Move statement to "${to}"?`)) return;
    setBusy(true);
    setError('');
    try {
      await statementApi.transition(id, to);
      await load();
    } catch (err) {
      setError(statementErrorMessage(err, 'Transition failed.'));
    } finally {
      setBusy(false);
    }
  };

  const submitAdjust = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await statementApi.adjust(id, parseFloat(adjust.amount), adjust.reason);
      setAdjust({ amount: '', reason: '' });
      await load();
    } catch (err) {
      setError(statementErrorMessage(err, 'Adjustment failed.'));
    } finally {
      setBusy(false);
    }
  };

  const downloadPdf = () => {
    const token = api.getToken ? localStorage.getItem('token') : null;
    window.open(statementApi.pdfUrl(id), '_blank');
  };

  if (loading) return <div className="text-slate-400">Loading statement…</div>;
  if (error && !statement) return <div className="alert-error">{error}</div>;
  if (!statement) return null;

  const actions = NEXT_ACTIONS[statement.status] || [];
  const grouped = {};
  (statement.lines || []).forEach((l) => {
    (grouped[l.line_type] = grouped[l.line_type] || []).push(l);
  });

  return (
    <div className="space-y-6 max-w-4xl">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">{statement.statement_number}</h1>
          <p className="text-slate-400 text-sm">{statement.owner?.name} · {statement.period?.start_date} → {statement.period?.end_date}</p>
        </div>
        <StatusBadge status={statement.status} />
      </div>

      {error && <div className="alert-error">{error}</div>}

      <div className="card">
        <h2 className="font-semibold mb-3">Reconciliation</h2>
        <div className="space-y-2 text-sm">
          <div className="flex justify-between"><span className="text-slate-400">Gross Income (accrual)</span><span>{fmt(statement.gross_income)}</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Management Fee ({statement.management_fee_percent}%)</span><span>({fmt(statement.management_fee)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Owner Expenses</span><span>({fmt(statement.owner_expenses)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Owner Maintenance</span><span>({fmt(statement.owner_maintenance)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Owner Utility Absorption</span><span>({fmt(statement.owner_utility_absorption)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Adjustments</span><span>{fmt(statement.adjustments_total)}</span></div>
          <div className="flex justify-between font-semibold text-base border-t border-slate-800 pt-2"><span>Net Amount</span><span>{fmt(statement.net_amount)}</span></div>
        </div>
        {(statement.approved_by || statement.finalized_by) && (
          <div className="text-xs text-slate-500 mt-3">
            {statement.approved_by && <div>Approved by {statement.approved_by} · {statement.approved_at}</div>}
            {statement.finalized_by && <div>Finalized by {statement.finalized_by} · {statement.finalized_at}</div>}
          </div>
        )}
      </div>

      <div className="card">
        <h2 className="font-semibold mb-3">Statement Lines ({statement.lines?.length || 0})</h2>
        {Object.entries(grouped).map(([type, lines]) => (
          <div key={type} className="mb-4">
            <h3 className="text-sm font-medium text-slate-300 mb-1">{TYPE_LABELS[type] || type} ({lines.length})</h3>
            <table className="w-full text-sm">
              <tbody>
                {lines.map((l) => (
                  <tr key={l.id} className="border-t border-slate-800">
                    <td className="py-1 text-slate-400 w-24">{l.line_date}</td>
                    <td className="py-1">{l.description}</td>
                    <td className="py-1 text-slate-400 w-32">{l.property || ''}</td>
                    <td className="py-1 text-right w-28">{fmt(l.amount)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ))}
        {(!statement.lines || statement.lines.length === 0) && <div className="empty-state">No lines.</div>}
      </div>

      <PermissionGuard permission="statements.adjust">
        {statement.status !== 'finalized' && (
          <form onSubmit={submitAdjust} className="card space-y-2">
            <h2 className="font-semibold">Add Adjustment</h2>
            <div className="flex gap-2">
              <input type="number" step="0.01" className="input" placeholder="Amount (+credit / −debit)" value={adjust.amount} onChange={(e) => setAdjust({ ...adjust, amount: e.target.value })} required />
              <input className="input flex-1" placeholder="Reason (required)" value={adjust.reason} onChange={(e) => setAdjust({ ...adjust, reason: e.target.value })} required minLength={5} />
              <button className="btn-secondary" disabled={busy}>{busy ? '…' : 'Add'}</button>
            </div>
          </form>
        )}
      </PermissionGuard>

      <div className="flex flex-wrap gap-2">
        {actions.map((a) => (
          <PermissionGuard key={a.to} permission={a.perm}>
            <button className="btn-primary" onClick={() => transition(a.to)} disabled={busy}>{busy ? 'Working…' : a.label}</button>
          </PermissionGuard>
        ))}
        <button className="btn-secondary" onClick={downloadPdf}>Download PDF</button>
      </div>
    </div>
  );
}
