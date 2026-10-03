import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { statementApi, statementPeriodApi, statementErrorMessage } from '../services/statementApi';
import api from '../../../services/api';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function StatementGeneratePage() {
  const navigate = useNavigate();
  const [owners, setOwners] = useState([]);
  const [periods, setPeriods] = useState([]);
  const [form, setForm] = useState({ owner_id: '', period_id: '' });
  const [preview, setPreview] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.get('/users', { params: { role: 'owner', per_page: 100 } })
      .then((res) => setOwners(res.data.data || []))
      .catch(() => {});
    statementPeriodApi.list({ per_page: 50 })
      .then(({ items }) => setPeriods(items.filter((p) => p.status !== 'locked')))
      .catch(() => {});
  }, []);

  const doPreview = async () => {
    setBusy(true);
    setError('');
    setPreview(null);
    try {
      const calc = await statementApi.preview({
        owner_id: parseInt(form.owner_id, 10),
        period_id: parseInt(form.period_id, 10),
      });
      setPreview(calc);
    } catch (err) {
      setError(statementErrorMessage(err, 'Preview failed.'));
    } finally {
      setBusy(false);
    }
  };

  const doGenerate = async () => {
    if (!window.confirm('Generate this owner statement?')) return;
    setBusy(true);
    setError('');
    try {
      const stmt = await statementApi.generate({
        owner_id: parseInt(form.owner_id, 10),
        period_id: parseInt(form.period_id, 10),
      });
      navigate(`/statements/${stmt.id}`);
    } catch (err) {
      setError(statementErrorMessage(err, 'Generation failed.'));
      setBusy(false);
    }
  };

  return (
    <div className="max-w-2xl space-y-4">
      <h1 className="text-xl font-semibold">Generate Owner Statement</h1>
      {error && <div className="alert-error">{error}</div>}

      <div className="card space-y-3">
        <div>
          <label className="label">Owner *</label>
          <select className="input w-full" value={form.owner_id} onChange={(e) => setForm({ ...form, owner_id: e.target.value, })} required>
            <option value="">Select owner…</option>
            {owners.map((o) => <option key={o.id} value={o.id}>{o.name} ({o.email})</option>)}
          </select>
        </div>
        <div>
          <label className="label">Statement Period *</label>
          <select className="input w-full" value={form.period_id} onChange={(e) => setForm({ ...form, period_id: e.target.value })} required>
            <option value="">Select period…</option>
            {periods.map((p) => <option key={p.id} value={p.id}>{p.start_date} → {p.end_date} ({p.status})</option>)}
          </select>
        </div>
        <div className="flex gap-2">
          <button className="btn-secondary" onClick={doPreview} disabled={busy || !form.owner_id || !form.period_id}>
            {busy ? 'Working…' : 'Preview'}
          </button>
          <button className="btn-primary" onClick={doGenerate} disabled={busy || !preview}>
            {busy ? 'Working…' : 'Generate Statement'}
          </button>
        </div>
      </div>

      {preview && (
        <div className="card space-y-2 text-sm">
          <h2 className="font-semibold">Preview — Full Reconciliation</h2>
          <div className="flex justify-between"><span className="text-slate-400">Gross Income</span><span>{fmt(preview.gross_income)}</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Management Fee ({preview.management_fee_percent}%)</span><span>({fmt(preview.management_fee)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Owner Expenses</span><span>({fmt(preview.owner_expenses)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Owner Maintenance</span><span>({fmt(preview.owner_maintenance)})</span></div>
          <div className="flex justify-between"><span className="text-slate-400">Owner Utility Absorption</span><span>({fmt(preview.owner_utility_absorption)})</span></div>
          <div className="flex justify-between font-semibold border-t border-slate-800 pt-2"><span>Net Amount</span><span>{fmt(preview.net_amount)}</span></div>
          <div className="text-xs text-slate-500">{preview.line_count} traceable lines will be created.</div>
        </div>
      )}
    </div>
  );
}
