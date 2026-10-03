import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { expenseApi, expenseErrorMessage, EXPENSE_CATEGORIES } from '../services/expenseApi';
import { propertyApi } from '../../property/services/propertyApi';

export default function ExpenseFormPage() {
  const navigate = useNavigate();
  const [properties, setProperties] = useState([]);
  const [form, setForm] = useState({
    property_id: '', category: 'repairs', description: '',
    expense_date: new Date().toISOString().slice(0, 10), amount: '', notes: '',
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
      const expense = await expenseApi.create({
        property_id: parseInt(form.property_id, 10),
        category: form.category,
        description: form.description,
        expense_date: form.expense_date,
        amount: parseFloat(form.amount),
        notes: form.notes || undefined,
      });
      navigate(`/expenses/${expense.id}`);
    } catch (err) {
      setError(expenseErrorMessage(err, 'Could not create expense.'));
    } finally {
      setSaving(false);
    }
  };

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  return (
    <div className="max-w-xl space-y-4">
      <h1 className="text-xl font-semibold">New Expense</h1>
      {error && <div className="alert-error">{error}</div>}
      <form onSubmit={submit} className="card space-y-3">
        <div>
          <label className="label">Property *</label>
          <select className="input w-full" value={form.property_id} onChange={set('property_id')} required>
            <option value="">Select property…</option>
            {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="label">Category *</label>
            <select className="input w-full" value={form.category} onChange={set('category')}>
              {EXPENSE_CATEGORIES.filter(Boolean).map((c) => <option key={c} value={c}>{c.replace('_', ' ')}</option>)}
            </select>
          </div>
          <div>
            <label className="label">Date *</label>
            <input type="date" className="input w-full" value={form.expense_date} onChange={set('expense_date')} required max={new Date().toISOString().slice(0, 10)} />
          </div>
        </div>
        <div>
          <label className="label">Amount (PKR) *</label>
          <input type="number" step="0.01" min="0.01" className="input w-full" value={form.amount} onChange={set('amount')} required />
        </div>
        <div>
          <label className="label">Description *</label>
          <textarea className="input w-full" rows={3} value={form.description} onChange={set('description')} required minLength={5} maxLength={2000} />
        </div>
        <div>
          <label className="label">Notes</label>
          <textarea className="input w-full" rows={2} value={form.notes} onChange={set('notes')} maxLength={1000} />
        </div>
        <button className="btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Create Expense'}</button>
      </form>
    </div>
  );
}
