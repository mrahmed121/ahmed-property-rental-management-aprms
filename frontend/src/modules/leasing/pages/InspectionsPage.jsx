import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { inspectionApi, leaseApi, leasingErrorMessage } from '../services/leasingApi';

const CONDITIONS = ['', 'excellent', 'good', 'fair', 'poor', 'damaged'];

function NewInspectionForm({ onDone }) {
  const [leases, setLeases] = useState([]);
  const [form, setForm] = useState({ lease_id: '', inspection_date: '', condition: 'good', notes: '', damage_observations: '' });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    (async () => {
      try {
        const [term, exp] = await Promise.all([
          leaseApi.list({ status: 'terminated', per_page: 100 }),
          leaseApi.list({ status: 'expired', per_page: 100 }),
        ]);
        setLeases([...(term.items || []), ...(exp.items || [])]);
      } catch { /* leave empty */ }
    })();
  }, []);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await inspectionApi.create({
        lease_id: parseInt(form.lease_id, 10),
        inspection_date: form.inspection_date,
        condition: form.condition,
        notes: form.notes || undefined,
        damage_observations: form.damage_observations || undefined,
      });
      onDone();
    } catch (err) {
      setError(leasingErrorMessage(err, 'Could not record the inspection.'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <form onSubmit={submit} className="aprms-card space-y-4">
      <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Record move-out inspection</h3>
      {error && <p className="text-sm text-red-400">{error}</p>}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Lease *</label>
          <select className="aprms-input" value={form.lease_id} onChange={set('lease_id')} required>
            <option value="">Select terminated/expired lease…</option>
            {leases.map((l) => (
              <option key={l.id} value={l.id}>{l.lease_number} · {l.tenant?.name} ({l.status})</option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Inspection date *</label>
          <input type="date" className="aprms-input" value={form.inspection_date} onChange={set('inspection_date')} required />
        </div>
      </div>
      <div>
        <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Condition *</label>
        <select className="aprms-input" value={form.condition} onChange={set('condition')}>
          {CONDITIONS.filter(Boolean).map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
      </div>
      <div>
        <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Damage observations</label>
        <textarea className="aprms-input" rows={2} value={form.damage_observations} onChange={set('damage_observations')} />
      </div>
      <div>
        <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Notes</label>
        <textarea className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} />
      </div>
      <div className="flex justify-end">
        <button type="submit" disabled={saving} className="aprms-btn-gold disabled:opacity-50">
          {saving ? 'Saving…' : 'Record Inspection'}
        </button>
      </div>
    </form>
  );
}

export default function InspectionsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [showNew, setShowNew] = useState(false);
  const [busy, setBusy] = useState(false);

  const filters = {
    condition: searchParams.get('condition') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await inspectionApi.list({
        per_page: 12,
        condition: filters.condition || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(leasingErrorMessage(err, 'Could not load inspections.'));
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

  const doReview = async (id) => {
    setBusy(true);
    try {
      await inspectionApi.review(id);
      setNotice('Inspection marked as reviewed.');
      load();
    } catch (err) {
      setNotice(leasingErrorMessage(err, 'Could not review the inspection.'));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('inspections.manage');

  return (
    <PermissionGuard permission="inspections.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Move-out Inspections</h2>
            <p className="mt-1 text-sm text-slate-400">Condition records for terminated and expired leases.</p>
          </div>
          {canManage && (
            <button className="aprms-btn-gold" onClick={() => setShowNew((s) => !s)}>
              {showNew ? 'Hide Form' : '+ Record Inspection'}
            </button>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {showNew && canManage && (
          <NewInspectionForm onDone={() => { setShowNew(false); setNotice('Inspection recorded.'); load(); }} />
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <select
            className="aprms-input"
            value={filters.condition}
            onChange={(e) => setFilter({ condition: e.target.value })}
          >
            {CONDITIONS.map((c) => (
              <option key={c} value={c}>{c || 'All conditions'}</option>
            ))}
          </select>
        </div>

        <DataTable
          columns={[
            {
              key: 'lease',
              label: 'Lease',
              render: (r) => (
                <Link to={`/leases/${r.lease_id}`} className="font-medium text-gold hover:text-gold-light">
                  {r.lease?.lease_number || `#${r.lease_id}`}
                </Link>
              ),
            },
            { key: 'tenant', label: 'Tenant', render: (r) => r.lease?.tenant?.name || '—' },
            { key: 'inspection_date', label: 'Date', render: (r) => <span className="text-slate-400">{r.inspection_date}</span> },
            { key: 'condition', label: 'Condition', render: (r) => <StatusBadge value={r.condition} /> },
            { key: 'review', label: 'Review', render: (r) => <StatusBadge value={r.review_status} /> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                canManage && r.review_status === 'pending' ? (
                  <button
                    className="aprms-btn-ghost !px-3 !py-1.5 !text-xs"
                    disabled={busy}
                    onClick={() => doReview(r.id)}
                  >
                    Mark Reviewed
                  </button>
                ) : <span className="text-xs text-slate-600">{r.inspector?.name || ''}</span>
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No inspections yet"
          emptyHint="Record a move-out inspection after a lease is terminated or expires. One inspection per lease."
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
