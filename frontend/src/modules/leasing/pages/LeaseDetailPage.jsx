import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import DocumentManager from '../../property/components/DocumentManager';
import { leaseApi, leasingErrorMessage } from '../services/leasingApi';

function Row({ label, children }) {
  return (
    <div className="flex flex-col gap-1 py-2 sm:flex-row sm:items-center">
      <dt className="w-44 shrink-0 text-xs font-medium uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-200">{children}</dd>
    </div>
  );
}

export default function LeaseDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [lease, setLease] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [confirmAction, setConfirmAction] = useState(null);
  const [renewForm, setRenewForm] = useState({ start_date: '', end_date: '', monthly_rent: '' });
  const [terminateForm, setTerminateForm] = useState({ termination_date: '', reason: '' });

  const load = async () => {
    setLoading(true);
    try {
      const l = await leaseApi.get(id);
      setLease(l);
      setError('');
      setRenewForm({
        start_date: l.end_date || '',
        end_date: '',
        monthly_rent: l.monthly_rent || '',
      });
    } catch (err) {
      setError(leasingErrorMessage(err, 'Could not load the lease.'));
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
      await load();
    } catch (err) {
      setNotice(leasingErrorMessage(err, 'Action failed.'));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('leases.manage');
  const canUploadDocs = hasPermission('documents.manage');

  if (loading) return <div className="mt-6"><Spinner /></div>;

  return (
    <PermissionGuard permission="leases.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/leases" className="text-sm text-gold hover:text-gold-light">← Leases</Link>
          {error ? (
            <div className="mt-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
          ) : lease && (
            <div className="mt-1 flex flex-wrap items-center gap-3">
              <h2 className="text-xl font-bold text-slate-100">{lease.lease_number}</h2>
              <StatusBadge value={lease.status} />
            </div>
          )}
          {lease && (
            <p className="mt-1 text-sm text-slate-400">
              {lease.tenant?.name} · {lease.property?.name}{lease.unit ? ` · Unit ${lease.unit.unit_number}` : ''}
            </p>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {lease && (
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div className="aprms-card">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Agreement</h3>
              <dl className="mt-2 divide-y divide-charcoal-700/50">
                <Row label="Term">{lease.start_date} → {lease.end_date}</Row>
                <Row label="Monthly rent">{Number(lease.monthly_rent).toLocaleString()}</Row>
                <Row label="Deposit">{lease.deposit_amount != null ? Number(lease.deposit_amount).toLocaleString() : '—'}</Row>
                <Row label="Activated">{lease.activated_at || '—'}</Row>
                {lease.terminated_at && <Row label="Terminated">{lease.terminated_at} — {lease.termination_reason}</Row>}
                {lease.previous_lease && (
                  <Row label="Renews">
                    <Link to={`/leases/${lease.previous_lease.id}`} className="text-gold hover:text-gold-light">
                      {lease.previous_lease.lease_number}
                    </Link>
                  </Row>
                )}
                {lease.successor && (
                  <Row label="Renewed by">
                    <Link to={`/leases/${lease.successor.id}`} className="text-gold hover:text-gold-light">
                      {lease.successor.lease_number}
                    </Link>
                  </Row>
                )}
              </dl>
              {lease.terms && (
                <div className="mt-3 border-t border-charcoal-700/60 pt-3">
                  <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Terms</p>
                  <p className="mt-1 whitespace-pre-wrap text-sm text-slate-300">{lease.terms}</p>
                </div>
              )}
            </div>

            <div className="space-y-5">
              {canManage && (
                <div className="aprms-card space-y-3">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Actions</h3>

                  {lease.status === 'draft' && (
                    <button
                      className="aprms-btn-gold w-full"
                      disabled={busy}
                      onClick={() => setConfirmAction('activate')}
                    >
                      Activate Lease
                    </button>
                  )}

                  {lease.status === 'active' && (
                    <>
                      <button
                        className="aprms-btn-ghost w-full"
                        disabled={busy}
                        onClick={() => setConfirmAction('renew')}
                      >
                        Renew (create successor)
                      </button>
                      <button
                        className="inline-flex w-full items-center justify-center rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2 text-sm font-medium text-red-300 hover:border-red-500/60"
                        disabled={busy}
                        onClick={() => setConfirmAction('terminate')}
                      >
                        Terminate Lease
                      </button>
                    </>
                  )}

                  {['terminated', 'expired', 'renewed'].includes(lease.status) && (
                    <p className="text-sm text-slate-500">
                      This lease is {lease.status}. History is preserved — create a new lease for a fresh term.
                    </p>
                  )}

                  {confirmAction === 'renew' && (
                    <div className="space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                      <p className="text-sm font-medium text-slate-200">Successor lease (original is preserved)</p>
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <label className="mb-1 block text-xs uppercase tracking-wider text-slate-500">Start</label>
                          <input type="date" className="aprms-input" value={renewForm.start_date}
                            onChange={(e) => setRenewForm((f) => ({ ...f, start_date: e.target.value }))} />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs uppercase tracking-wider text-slate-500">End</label>
                          <input type="date" className="aprms-input" value={renewForm.end_date}
                            onChange={(e) => setRenewForm((f) => ({ ...f, end_date: e.target.value }))} />
                        </div>
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-500">Monthly rent</label>
                        <input type="number" min="0" step="0.01" className="aprms-input" value={renewForm.monthly_rent}
                          onChange={(e) => setRenewForm((f) => ({ ...f, monthly_rent: e.target.value }))} />
                      </div>
                      <div className="flex gap-2">
                        <button
                          className="aprms-btn-gold"
                          disabled={busy || !renewForm.start_date || !renewForm.end_date || !renewForm.monthly_rent}
                          onClick={() => run(
                            () => leaseApi.renew(lease.id, {
                              start_date: renewForm.start_date,
                              end_date: renewForm.end_date,
                              monthly_rent: parseFloat(renewForm.monthly_rent),
                            }),
                            'Successor lease created as a draft. Activate it to take over.',
                          )}
                        >
                          Create Successor
                        </button>
                        <button className="aprms-btn-ghost" onClick={() => setConfirmAction(null)}>Cancel</button>
                      </div>
                    </div>
                  )}

                  {confirmAction === 'terminate' && (
                    <div className="space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                      <p className="text-sm font-medium text-slate-200">Terminate this lease</p>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-500">Termination date *</label>
                        <input type="date" className="aprms-input" value={terminateForm.termination_date}
                          onChange={(e) => setTerminateForm((f) => ({ ...f, termination_date: e.target.value }))} />
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-500">Reason *</label>
                        <textarea className="aprms-input" rows={2} value={terminateForm.reason}
                          onChange={(e) => setTerminateForm((f) => ({ ...f, reason: e.target.value }))}
                          placeholder="Why is this lease ending?" />
                      </div>
                      <div className="flex gap-2">
                        <button
                          className="inline-flex items-center rounded-lg bg-red-800 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                          disabled={busy || !terminateForm.termination_date || !terminateForm.reason.trim()}
                          onClick={() => run(
                            () => leaseApi.terminate(lease.id, terminateForm),
                            'Lease terminated. Unit vacancy restored.',
                          )}
                        >
                          Confirm Termination
                        </button>
                        <button className="aprms-btn-ghost" onClick={() => setConfirmAction(null)}>Cancel</button>
                      </div>
                      <p className="text-xs text-slate-500">Deposit settlement is prepared in a later financial phase — this records the termination only.</p>
                    </div>
                  )}
                </div>
              )}

              <div className="aprms-card">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Documents</h3>
                <div className="mt-3">
                  <DocumentManager
                    parentType="lease"
                    parentId={lease.id}
                    canManage={canUploadDocs}
                  />
                </div>
              </div>
            </div>
          </div>
        )}

        <ConfirmDialog
          open={confirmAction === 'activate'}
          title="Activate this lease?"
          message="The unit will become occupied and the tenant's status becomes active. Overlapping active leases are rejected."
          confirmLabel="Activate"
          busy={busy}
          onConfirm={() => run(() => leaseApi.activate(lease.id), 'Lease activated. Unit is now occupied.')}
          onCancel={() => setConfirmAction(null)}
        />
      </div>
    </PermissionGuard>
  );
}
