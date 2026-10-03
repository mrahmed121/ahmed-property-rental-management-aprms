import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import { applicationApi, tenantApi, leasingErrorMessage } from '../services/leasingApi';
import { propertyApi, unitApi } from '../../property/services/propertyApi';

const NEXT_STEPS = {
  draft: ['submitted'],
  submitted: ['under_review'],
  under_review: ['screening'],
  screening: [],
  approved: [],
  rejected: [],
};

function NewApplicationForm() {
  const navigate = useNavigate();
  const [searchParams] = useState(() => new URLSearchParams(window.location.search));
  const [tenants, setTenants] = useState([]);
  const [properties, setProperties] = useState([]);
  const [units, setUnits] = useState([]);
  const [form, setForm] = useState({
    tenant_id: searchParams.get('tenant_id') || '',
    property_id: '', unit_id: '', notes: '',
  });
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');

  useEffect(() => {
    tenantApi.list({ per_page: 100 }).then((r) => setTenants(r.items)).catch(() => {});
    propertyApi.list({ per_page: 100, status: 'active' }).then((r) => setProperties(r.items)).catch(() => {});
  }, []);

  useEffect(() => {
    if (!form.property_id) { setUnits([]); return; }
    unitApi.list({ property_id: form.property_id, per_page: 200 })
      .then((r) => setUnits(r.items.filter((u) => ['vacant', 'reserved'].includes(u.status))))
      .catch(() => {});
  }, [form.property_id]);

  const set = (k) => (e) => setForm((f) => ({ ...f, [k]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      const saved = await applicationApi.create({
        tenant_id: parseInt(form.tenant_id, 10),
        property_id: parseInt(form.property_id, 10),
        unit_id: form.unit_id ? parseInt(form.unit_id, 10) : undefined,
        notes: form.notes || undefined,
      });
      navigate(`/applications/${saved.id}`);
    } catch (err) {
      if (err?.response?.status === 422) setErrors(err.response.data.errors || {});
      else setError(leasingErrorMessage(err, 'Could not create the application.'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="mx-auto max-w-2xl space-y-5">
      <div>
        <Link to="/applications" className="text-sm text-gold hover:text-gold-light">← Applications</Link>
        <h2 className="mt-1 text-xl font-bold text-slate-100">New Application</h2>
      </div>
      {error && <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>}
      <form onSubmit={submit} className="aprms-card space-y-4">
        <div>
          <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Tenant *</label>
          <select className="aprms-input" value={form.tenant_id} onChange={set('tenant_id')} required>
            <option value="">Select tenant…</option>
            {tenants.map((t) => <option key={t.id} value={t.id}>{t.name} ({t.status})</option>)}
          </select>
          {errors.tenant_id && <p className="mt-1 text-xs text-red-400">{errors.tenant_id[0]}</p>}
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Property *</label>
          <select className="aprms-input" value={form.property_id} onChange={set('property_id')} required>
            <option value="">Select property…</option>
            {properties.map((p) => <option key={p.id} value={p.id}>{p.name} — {p.city}</option>)}
          </select>
          {errors.property_id && <p className="mt-1 text-xs text-red-400">{errors.property_id[0]}</p>}
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Unit (optional)</label>
          <select className="aprms-input" value={form.unit_id} onChange={set('unit_id')}>
            <option value="">No specific unit</option>
            {units.map((u) => <option key={u.id} value={u.id}>{u.unit_number} ({u.status})</option>)}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">Notes</label>
          <textarea className="aprms-input" rows={2} value={form.notes} onChange={set('notes')} />
        </div>
        <div className="flex justify-end gap-2">
          <Link to="/applications" className="aprms-btn-ghost">Cancel</Link>
          <button type="submit" disabled={saving} className="aprms-btn-gold disabled:opacity-50">
            {saving ? 'Creating…' : 'Create Application'}
          </button>
        </div>
      </form>
    </div>
  );
}

export default function ApplicationDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const navigate = useNavigate();

  if (id === 'new') {
    return (
      <PermissionGuard permission="applications.manage" showForbidden>
        <NewApplicationForm />
      </PermissionGuard>
    );
  }

  return (
    <PermissionGuard permission="applications.view" showForbidden>
      <ReviewScreen id={id} canManage={hasPermission('applications.manage')} canScreen={hasPermission('screening.manage')} navigate={navigate} />
    </PermissionGuard>
  );
}

function ReviewScreen({ id, canManage, canScreen, navigate }) {
  const [app, setApp] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [decision, setDecision] = useState('');
  const [decisionNotes, setDecisionNotes] = useState('');
  const [confirmAction, setConfirmAction] = useState(null);
  const [screenNotes, setScreenNotes] = useState('');

  const load = async () => {
    setLoading(true);
    try {
      setApp(await applicationApi.get(id));
      setError('');
    } catch (err) {
      setError(leasingErrorMessage(err, 'Could not load the application.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const run = async (fn, okMsg) => {
    setBusy(true);
    setNotice('');
    try {
      await fn();
      setNotice(okMsg);
      setConfirmAction(null);
      setDecision('');
      setDecisionNotes('');
      await load();
    } catch (err) {
      setNotice(leasingErrorMessage(err, 'Action failed.'));
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <div className="mt-6"><Spinner /></div>;
  if (error) return <div className="rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>;
  if (!app) return null;

  const nextSteps = NEXT_STEPS[app.status] || [];

  return (
    <div className="space-y-5">
      <div>
        <Link to="/applications" className="text-sm text-gold hover:text-gold-light">← Applications</Link>
        <div className="mt-1 flex flex-wrap items-center gap-3">
          <h2 className="text-xl font-bold text-slate-100">{app.application_number}</h2>
          <StatusBadge value={app.status} />
          <StatusBadge value={app.screening_status} />
        </div>
        <p className="mt-1 text-sm text-slate-400">
          {app.tenant?.name} · {app.property?.name}{app.unit ? ` · Unit ${app.unit.unit_number}` : ''}
        </p>
      </div>

      {notice && (
        <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
      )}

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <div className="aprms-card space-y-3">
          <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Workflow</h3>
          <div className="flex flex-wrap gap-2">
            {['draft', 'submitted', 'under_review', 'screening', 'approved', 'rejected'].map((s) => (
              <span
                key={s}
                className={`rounded-full px-3 py-1 text-xs ring-1 ${
                  app.status === s
                    ? 'bg-gold/15 text-gold ring-gold/40'
                    : 'bg-charcoal-800 text-slate-500 ring-charcoal-700'
                }`}
              >
                {s.replace(/_/g, ' ')}
              </span>
            ))}
          </div>

          {canManage && nextSteps.length > 0 && (
            <div className="space-y-3 border-t border-charcoal-700/60 pt-4">
              <p className="text-sm text-slate-300">Move to:</p>
              {nextSteps.includes('screening') ? (
                <button
                  className="aprms-btn-gold"
                  disabled={busy || !canScreen}
                  title={canScreen ? '' : 'You lack screening.manage permission'}
                  onClick={() => run(() => applicationApi.startScreening(app.id), 'Screening started.')}
                >
                  Start Screening
                </button>
              ) : (
                nextSteps.map((s) => (
                  <button
                    key={s}
                    className="aprms-btn-ghost mr-2"
                    disabled={busy}
                    onClick={() => {
                      if (s === 'approved' || s === 'rejected') { setDecision(s); }
                      else run(() => applicationApi.transition(app.id, s), `Moved to ${s.replace(/_/g, ' ')}.`);
                    }}
                  >
                    {s.replace(/_/g, ' ')}
                  </button>
                ))
              )}
              {decision && (
                <div className="space-y-2 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                  <p className="text-sm font-medium text-slate-200">
                    {decision === 'approved' ? 'Approve application' : 'Reject application'}
                  </p>
                  <textarea
                    className="aprms-input"
                    rows={2}
                    placeholder="Decision notes…"
                    value={decisionNotes}
                    onChange={(e) => setDecisionNotes(e.target.value)}
                  />
                  <div className="flex gap-2">
                    <button
                      className="aprms-btn-gold"
                      disabled={busy}
                      onClick={() => run(
                        () => applicationApi.transition(app.id, decision, { decision_notes: decisionNotes || undefined }),
                        decision === 'approved' ? 'Application approved.' : 'Application rejected.',
                      )}
                    >
                      Confirm {decision}
                    </button>
                    <button className="aprms-btn-ghost" onClick={() => setDecision('')}>Cancel</button>
                  </div>
                  {decision === 'approved' && (
                    <p className="text-xs text-slate-500">Requires completed screening with a clear result and verified tenant KYC.</p>
                  )}
                </div>
              )}
            </div>
          )}

          {app.status === 'approved' && canManage && (
            <div className="border-t border-charcoal-700/60 pt-4">
              <Link to={`/leases/new?application_id=${app.id}`} className="aprms-btn-gold">Create Lease from Application</Link>
            </div>
          )}
        </div>

        <div className="aprms-card space-y-3">
          <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Screening</h3>
          <dl className="divide-y divide-charcoal-700/50 text-sm">
            <div className="flex justify-between py-2"><dt className="text-slate-500">Status</dt><dd><StatusBadge value={app.screening_status} /></dd></div>
            <div className="flex justify-between py-2"><dt className="text-slate-500">Screened by</dt><dd className="text-slate-200">{app.screened_by?.name || '—'}</dd></div>
            <div className="flex justify-between py-2"><dt className="text-slate-500">Screened at</dt><dd className="text-slate-200">{app.screened_at || '—'}</dd></div>
            <div className="flex justify-between py-2"><dt className="text-slate-500">Reviewed by</dt><dd className="text-slate-200">{app.reviewed_by?.name || '—'}</dd></div>
          </dl>
          {app.screening_notes && <p className="text-sm text-slate-400">{app.screening_notes}</p>}
          {app.decision_notes && <p className="text-sm text-slate-300">Decision: {app.decision_notes}</p>}

          {canScreen && app.status === 'screening' && app.screening_status === 'in_progress' && (
            <div className="space-y-2 border-t border-charcoal-700/60 pt-4">
              <textarea
                className="aprms-input"
                rows={2}
                placeholder="Screening notes…"
                value={screenNotes}
                onChange={(e) => setScreenNotes(e.target.value)}
              />
              <div className="flex gap-2">
                <button
                  className="aprms-btn-gold"
                  disabled={busy}
                  onClick={() => setConfirmAction('clear')}
                >
                  Mark Clear
                </button>
                <button
                  className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2 text-sm font-medium text-red-300 hover:border-red-500/60"
                  disabled={busy}
                  onClick={() => setConfirmAction('flagged')}
                >
                  Flag Issues
                </button>
              </div>
            </div>
          )}
        </div>
      </div>

      <ConfirmDialog
        open={!!confirmAction}
        title={confirmAction === 'clear' ? 'Mark screening clear?' : 'Flag screening issues?'}
        message={confirmAction === 'clear'
          ? 'A clear result allows the application to be approved (tenant KYC must also be verified).'
          : 'A flagged screening blocks approval of this application.'}
        confirmLabel={confirmAction === 'clear' ? 'Mark Clear' : 'Flag'}
        busy={busy}
        onConfirm={() => run(
          () => applicationApi.decideScreening(app.id, { clear: confirmAction === 'clear', notes: screenNotes || undefined }),
          confirmAction === 'clear' ? 'Screening marked clear.' : 'Screening flagged.',
        )}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
