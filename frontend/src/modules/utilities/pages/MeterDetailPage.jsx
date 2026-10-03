import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import StatusBadge from '../../property/components/StatusBadge';
import { meterApi, utilityBillApi, utilityErrorMessage } from '../services/utilityApi';

const fmt = (n) => `₨${Number(n || 0).toLocaleString('en-PK', { minimumFractionDigits: 2 })}`;

export default function MeterDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const [meter, setMeter] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [reading, setReading] = useState({ reading_date: '', reading_value: '', notes: '' });
  const [saving, setSaving] = useState(false);
  const [billForm, setBillForm] = useState({ period_start: '', period_end: '', rate: '', fixed_charge: '', tax_amount: '' });
  const [preview, setPreview] = useState(null);
  const [billBusy, setBillBusy] = useState(false);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      setMeter(await meterApi.get(id));
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not load meter.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const submitReading = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await meterApi.recordReading(id, {
        reading_date: reading.reading_date,
        reading_value: parseFloat(reading.reading_value),
        notes: reading.notes || undefined,
      });
      setReading({ reading_date: '', reading_value: '', notes: '' });
      await load();
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not record reading.'));
    } finally {
      setSaving(false);
    }
  };

  const doPreview = async () => {
    setBillBusy(true);
    setError('');
    try {
      const p = await utilityBillApi.preview(id, {
        period_start: billForm.period_start,
        period_end: billForm.period_end,
        rate: parseFloat(billForm.rate),
        fixed_charge: parseFloat(billForm.fixed_charge || 0),
        tax_amount: parseFloat(billForm.tax_amount || 0),
      });
      setPreview(p);
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not preview bill.'));
    } finally {
      setBillBusy(false);
    }
  };

  const doGenerate = async () => {
    if (!window.confirm('Generate this utility bill?')) return;
    setBillBusy(true);
    setError('');
    try {
      const bill = await utilityBillApi.generate(id, {
        period_start: billForm.period_start,
        period_end: billForm.period_end,
        rate: parseFloat(billForm.rate),
        fixed_charge: parseFloat(billForm.fixed_charge || 0),
        tax_amount: parseFloat(billForm.tax_amount || 0),
      });
      navigate(`/utilities/bills/${bill.id}`);
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not generate bill.'));
      setBillBusy(false);
    }
  };

  if (loading) return <div className="text-slate-400">Loading meter…</div>;
  if (error && !meter) return <div className="alert-error">{error}</div>;
  if (!meter) return null;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold">{meter.meter_number}</h1>
          <p className="text-slate-400 text-sm capitalize">{meter.utility_type} · {meter.property?.name} {meter.unit ? `· Unit ${meter.unit.unit_number}` : ''}</p>
        </div>
        <StatusBadge status={meter.status} />
      </div>

      {error && <div className="alert-error">{error}</div>}

      <div className="grid md:grid-cols-2 gap-6">
        <div className="card">
          <h2 className="font-semibold mb-3">Reading History</h2>
          {meter.readings?.length === 0 ? (
            <div className="empty-state">No readings recorded yet.</div>
          ) : (
            <table className="w-full text-sm">
              <thead><tr className="text-left text-slate-400"><th className="py-1">Date</th><th>Reading</th><th>Source</th></tr></thead>
              <tbody>
                {meter.readings?.map((r) => (
                  <tr key={r.id} className="border-t border-slate-800">
                    <td className="py-1">{r.reading_date}</td>
                    <td>{Number(r.reading_value).toLocaleString()}</td>
                    <td className="capitalize text-slate-400">{r.source}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          <PermissionGuard permission="utilities.manage">
            <form onSubmit={submitReading} className="mt-4 space-y-2 border-t border-slate-800 pt-4">
              <h3 className="font-medium text-sm">Record Reading</h3>
              <div className="flex gap-2">
                <input type="date" className="input" value={reading.reading_date} onChange={(e) => setReading({ ...reading, reading_date: e.target.value })} required max={new Date().toISOString().slice(0, 10)} />
                <input type="number" step="0.01" min="0" className="input" placeholder="Reading value" value={reading.reading_value} onChange={(e) => setReading({ ...reading, reading_value: e.target.value })} required />
              </div>
              <input className="input w-full" placeholder="Notes (optional)" value={reading.notes} onChange={(e) => setReading({ ...reading, notes: e.target.value })} />
              <button className="btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Record Reading'}</button>
            </form>
          </PermissionGuard>
        </div>

        <PermissionGuard permission="utilities.bill">
          <div className="card">
            <h2 className="font-semibold mb-3">Generate Bill</h2>
            <div className="space-y-2">
              <div className="flex gap-2">
                <input type="date" className="input" value={billForm.period_start} onChange={(e) => setBillForm({ ...billForm, period_start: e.target.value })} required />
                <input type="date" className="input" value={billForm.period_end} onChange={(e) => setBillForm({ ...billForm, period_end: e.target.value })} required />
              </div>
              <div className="flex gap-2">
                <input type="number" step="0.0001" min="0" className="input" placeholder="Rate per unit" value={billForm.rate} onChange={(e) => setBillForm({ ...billForm, rate: e.target.value })} required />
                <input type="number" step="0.01" min="0" className="input" placeholder="Fixed charge" value={billForm.fixed_charge} onChange={(e) => setBillForm({ ...billForm, fixed_charge: e.target.value })} />
                <input type="number" step="0.01" min="0" className="input" placeholder="Tax" value={billForm.tax_amount} onChange={(e) => setBillForm({ ...billForm, tax_amount: e.target.value })} />
              </div>
              <div className="flex gap-2">
                <button type="button" className="btn-secondary" onClick={doPreview} disabled={billBusy || !billForm.rate}>Preview</button>
                <button type="button" className="btn-primary" onClick={doGenerate} disabled={billBusy || !preview}>Generate Bill</button>
              </div>
              {preview && (
                <div className="text-sm space-y-1 border-t border-slate-800 pt-3">
                  <div className="flex justify-between"><span className="text-slate-400">Previous reading</span><span>{Number(preview.previous_reading).toLocaleString()}</span></div>
                  <div className="flex justify-between"><span className="text-slate-400">Current reading</span><span>{Number(preview.current_reading).toLocaleString()}</span></div>
                  <div className="flex justify-between"><span className="text-slate-400">Consumption</span><span>{Number(preview.consumption).toLocaleString()} {preview.unit}</span></div>
                  <div className="flex justify-between font-semibold"><span>Total</span><span>{fmt(preview.total)}</span></div>
                </div>
              )}
            </div>

            {meter.bills?.length > 0 && (
              <div className="mt-4 border-t border-slate-800 pt-3">
                <h3 className="font-medium text-sm mb-2">Recent Bills</h3>
                {meter.bills.map((b) => (
                  <div key={b.id} className="flex justify-between text-sm py-1">
                    <Link to={`/utilities/bills/${b.id}`} className="text-blue-400 hover:underline">{b.bill_number}</Link>
                    <span className="text-slate-400">{fmt(b.total)} · <StatusBadge status={b.status} /></span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </PermissionGuard>
      </div>
    </div>
  );
}
