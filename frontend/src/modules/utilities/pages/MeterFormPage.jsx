import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { meterApi, utilityErrorMessage } from '../services/utilityApi';
import { propertyApi } from '../../property/services/propertyApi';

export default function MeterFormPage() {
  const navigate = useNavigate();
  const [properties, setProperties] = useState([]);
  const [form, setForm] = useState({
    property_id: '', meter_number: '', utility_type: 'electricity',
    unit_of_measure: 'kWh', installation_date: '', opening_reading: '0', notes: '',
  });
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    propertyApi.list({ per_page: 100 }).then(({ items }) => setProperties(items)).catch(() => {});
  }, []);

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const meter = await meterApi.create({
        property_id: parseInt(form.property_id, 10),
        meter_number: form.meter_number,
        utility_type: form.utility_type,
        unit_of_measure: form.unit_of_measure,
        installation_date: form.installation_date || undefined,
        opening_reading: parseFloat(form.opening_reading || 0),
        notes: form.notes || undefined,
      });
      navigate(`/utilities/meters/${meter.id}`);
    } catch (err) {
      setError(utilityErrorMessage(err, 'Could not register meter.'));
    } finally {
      setSaving(false);
    }
  };

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  return (
    <div className="max-w-xl space-y-4">
      <h1 className="text-xl font-semibold">Register Meter</h1>
      {error && <div className="alert-error">{error}</div>}
      <form onSubmit={submit} className="card space-y-3">
        <div>
          <label className="label">Property *</label>
          <select className="input w-full" value={form.property_id} onChange={set('property_id')} required>
            <option value="">Select property…</option>
            {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
        <div>
          <label className="label">Meter Number *</label>
          <input className="input w-full" value={form.meter_number} onChange={set('meter_number')} required maxLength={100} />
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="label">Utility Type *</label>
            <select className="input w-full" value={form.utility_type} onChange={set('utility_type')}>
              <option value="electricity">Electricity</option>
              <option value="gas">Gas</option>
              <option value="water">Water</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label className="label">Unit of Measure</label>
            <input className="input w-full" value={form.unit_of_measure} onChange={set('unit_of_measure')} maxLength={20} />
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="label">Installation Date</label>
            <input type="date" className="input w-full" value={form.installation_date} onChange={set('installation_date')} max={new Date().toISOString().slice(0, 10)} />
          </div>
          <div>
            <label className="label">Opening Reading</label>
            <input type="number" step="0.01" min="0" className="input w-full" value={form.opening_reading} onChange={set('opening_reading')} />
          </div>
        </div>
        <div>
          <label className="label">Notes</label>
          <textarea className="input w-full" rows={3} value={form.notes} onChange={set('notes')} maxLength={1000} />
        </div>
        <button className="btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Register Meter'}</button>
      </form>
    </div>
  );
}
