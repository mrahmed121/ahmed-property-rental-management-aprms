import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import PermissionGuard from '../../../components/common/PermissionGuard';
import { propertyApi } from '../../property/services/propertyApi';
import { ticketApi, SLA_LABELS, maintenanceErrorMessage } from '../services/maintenanceApi';

const CATEGORIES = ['plumbing', 'electrical', 'carpentry', 'painting', 'appliance', 'hvac', 'general'];
const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

export default function TicketFormPage() {
  const navigate = useNavigate();
  const [properties, setProperties] = useState([]);
  const [form, setForm] = useState({
    property_id: '', unit_id: '', category: 'general',
    description: '', priority: 'normal', notes: '',
  });
  const [posting, setPosting] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    propertyApi.list({ per_page: 100 }).then((r) => setProperties(r.items)).catch(() => {});
  }, []);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setPosting(true);
    setErrors({});
    setError('');
    try {
      const saved = await ticketApi.create({
        property_id: parseInt(form.property_id, 10),
        unit_id: form.unit_id ? parseInt(form.unit_id, 10) : undefined,
        category: form.category,
        description: form.description,
        priority: form.priority,
        notes: form.notes || undefined,
      });
      navigate(`/maintenance/tickets/${saved.id}`);
    } catch (err) {
      if (err?.response?.status === 422 && err.response.data.errors) {
        setErrors(err.response.data.errors);
      } else {
        setError(maintenanceErrorMessage(err, 'Could not create the ticket.'));
      }
    } finally {
      setPosting(false);
    }
  };

  const labelCls = 'mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400';
  const err = (k) => errors[k] && <p className="mt-1 text-xs text-red-400">{errors[k][0]}</p>;

  return (
    <PermissionGuard permission="maintenance.report" showForbidden>
      <div className="mx-auto max-w-2xl space-y-5">
        <div>
          <Link to="/maintenance/tickets" className="text-sm text-gold hover:text-gold-light">← Tickets</Link>
          <h2 className="mt-1 text-xl font-bold text-slate-100">Report Maintenance Issue</h2>
          <p className="mt-1 text-sm text-slate-400">Describe the problem clearly — photos can be attached after creation.</p>
        </div>

        {error && (
          <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
        )}

        <form onSubmit={submit} className="aprms-card space-y-4">
          <div>
            <label className={labelCls} htmlFor="tk-property">Property *</label>
            <select id="tk-property" className="aprms-input" value={form.property_id} onChange={set('property_id')} required>
              <option value="">Select property…</option>
              {properties.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
            {err('property_id')}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls} htmlFor="tk-category">Category *</label>
              <select id="tk-category" className="aprms-input" value={form.category} onChange={set('category')}>
                {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
              </select>
              {err('category')}
            </div>
            <div>
              <label className={labelCls} htmlFor="tk-priority">Priority *</label>
              <select id="tk-priority" className="aprms-input" value={form.priority} onChange={set('priority')}>
                {PRIORITIES.map((p) => <option key={p} value={p}>{p} (SLA {SLA_LABELS[p]})</option>)}
              </select>
              {err('priority')}
            </div>
          </div>

          <div>
            <label className={labelCls} htmlFor="tk-desc">Description *</label>
            <textarea
              id="tk-desc" className="aprms-input" rows={4}
              value={form.description} onChange={set('description')} required
              placeholder="What is broken, where exactly, and when did it start? (min 10 characters)"
            />
            {err('description')}
          </div>

          <div>
            <label className={labelCls} htmlFor="tk-notes">Notes</label>
            <textarea id="tk-notes" className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} />
          </div>

          <div className="flex justify-end gap-2 border-t border-charcoal-700/60 pt-4">
            <Link to="/maintenance/tickets" className="aprms-btn-ghost">Cancel</Link>
            <button type="submit" disabled={posting} className="aprms-btn-gold disabled:opacity-50">
              {posting ? 'Creating…' : 'Create Ticket'}
            </button>
          </div>
        </form>
      </div>
    </PermissionGuard>
  );
}
