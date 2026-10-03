import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import DataTable from '../../property/components/DataTable';
import { vendorApi, maintenanceErrorMessage } from '../services/maintenanceApi';

export default function VendorsPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [busy, setBusy] = useState(false);
  const [form, setForm] = useState({ name: '', contact_person: '', phone: '', category: '' });

  const page = parseInt(searchParams.get('page') || '1', 10);

  const load = async () => {
    setLoading(true);
    try {
      const { items, meta } = await vendorApi.list({ per_page: 12, page });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(maintenanceErrorMessage(err, 'Could not load vendors.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [page]);

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      await vendorApi.create(form);
      setNotice('Vendor created.');
      setShowForm(false);
      setForm({ name: '', contact_person: '', phone: '', category: '' });
      load();
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('vendors.manage');
  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));
  const labelCls = 'mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400';

  return (
    <PermissionGuard permission="vendors.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Vendors</h2>
            <p className="mt-1 text-sm text-slate-400">Agency-scoped service providers for maintenance work.</p>
          </div>
          {canManage && !showForm && (
            <button className="aprms-btn-gold" onClick={() => setShowForm(true)}>+ New Vendor</button>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {showForm && (
          <form onSubmit={submit} className="aprms-card space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label className={labelCls}>Name *</label>
                <input className="aprms-input" value={form.name} onChange={set('name')} required />
              </div>
              <div>
                <label className={labelCls}>Contact person</label>
                <input className="aprms-input" value={form.contact_person} onChange={set('contact_person')} />
              </div>
              <div>
                <label className={labelCls}>Phone</label>
                <input className="aprms-input" value={form.phone} onChange={set('phone')} />
              </div>
              <div>
                <label className={labelCls}>Category</label>
                <input className="aprms-input" value={form.category} onChange={set('category')} placeholder="plumbing, electrical…" />
              </div>
            </div>
            <div className="flex gap-2">
              <button type="submit" className="aprms-btn-gold" disabled={busy}>
                {busy ? 'Saving…' : 'Create Vendor'}
              </button>
              <button type="button" className="aprms-btn-ghost" onClick={() => setShowForm(false)}>Cancel</button>
            </div>
          </form>
        )}

        <DataTable
          columns={[
            { key: 'name', label: 'Name', render: (r) => <span className="font-medium text-slate-100">{r.name}</span> },
            { key: 'contact', label: 'Contact', render: (r) => <span className="text-slate-400">{r.contact_person || '—'}</span> },
            { key: 'phone', label: 'Phone', render: (r) => <span className="text-slate-400">{r.phone || '—'}</span> },
            { key: 'category', label: 'Category', render: (r) => <span className="text-slate-400">{r.category || '—'}</span> },
            { key: 'status', label: 'Status', render: (r) => <span className="text-slate-400">{r.status}</span> },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No vendors yet"
          emptyHint="Add the plumbers, electricians, and contractors your agency works with."
          onPage={(p) => {
            const next = new URLSearchParams(searchParams);
            next.set('page', p);
            setSearchParams(next);
          }}
        />
      </div>
    </PermissionGuard>
  );
}
