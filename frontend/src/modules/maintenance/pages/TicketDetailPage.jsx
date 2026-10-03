import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import { ticketApi, vendorApi, maintenanceErrorMessage } from '../services/maintenanceApi';
import { formatPKR } from '../../billing/services/billingApi';

function Row({ label, children }) {
  return (
    <div className="flex flex-col gap-1 py-2 sm:flex-row sm:items-center">
      <dt className="w-44 shrink-0 text-xs font-medium uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-200">{children}</dd>
    </div>
  );
}

const NEXT_ACTIONS = {
  open: [['triaged', 'Triage']],
  triaged: [['assigned', 'Mark Assigned']],
  assigned: [],
  quoted: [],
  approval_pending: [],
  approved: [['in_progress', 'Start Work']],
  in_progress: [['completed', 'Mark Completed']],
  completed: [],
  verified: [['closed', 'Close Ticket']],
};

export default function TicketDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [ticket, setTicket] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);

  const [showAssign, setShowAssign] = useState(false);
  const [technicianId, setTechnicianId] = useState('');
  const [showQuote, setShowQuote] = useState(false);
  const [quote, setQuote] = useState({
    provider: '', labor_cost: '', materials_cost: '',
    attribution: 'owner', attribution_reason: '', notes: '',
  });
  const [vendors, setVendors] = useState([]);
  const [vendorId, setVendorId] = useState('');
  const [showWorkLog, setShowWorkLog] = useState(false);
  const [workLog, setWorkLog] = useState({ notes: '', parts_materials: '', labor_notes: '' });
  const [showVerify, setShowVerify] = useState(false);
  const [verifyResult, setVerifyResult] = useState('passed');
  const [verifyNotes, setVerifyNotes] = useState('');
  const [confirmClose, setConfirmClose] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      setTicket(await ticketApi.get(id));
      setError('');
    } catch (err) {
      setError(maintenanceErrorMessage(err, 'Could not load the ticket.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);
  useEffect(() => {
    if (hasPermission('vendors.view')) {
      vendorApi.list({ per_page: 100 }).then((r) => setVendors(r.items)).catch(() => {});
    }
  }, [hasPermission]);

  const done = async (msg, reset) => {
    if (msg) setNotice(msg);
    if (reset) reset();
    await load();
  };

  const doTransition = async (to) => {
    if (to === 'closed') { setConfirmClose(true); return; }
    setBusy(true);
    try {
      await ticketApi.transition(id, to);
      done(`Ticket moved to ${to.replace(/_/g, ' ')}.`);
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doClose = async () => {
    setBusy(true);
    try {
      await ticketApi.transition(id, 'closed');
      setConfirmClose(false);
      done('Ticket closed.');
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doAssign = async () => {
    setBusy(true);
    try {
      await ticketApi.assign(id, parseInt(technicianId, 10));
      done('Ticket assigned.', () => { setShowAssign(false); setTechnicianId(''); });
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doQuote = async () => {
    setBusy(true);
    try {
      await ticketApi.createQuote(id, {
        provider: quote.provider || undefined,
        vendor_id: vendorId ? parseInt(vendorId, 10) : undefined,
        labor_cost: parseFloat(quote.labor_cost || '0'),
        materials_cost: parseFloat(quote.materials_cost || '0'),
        attribution: quote.attribution,
        attribution_reason: quote.attribution_reason,
        notes: quote.notes || undefined,
      });
      done('Quote submitted for approval.', () => {
        setShowQuote(false);
        setQuote({ provider: '', labor_cost: '', materials_cost: '', attribution: 'owner', attribution_reason: '', notes: '' });
        setVendorId('');
      });
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doDecideQuote = async (quoteId, decision) => {
    setBusy(true);
    try {
      await ticketApi.decideQuote(quoteId, decision);
      done(`Quote ${decision}.`);
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doWorkLog = async () => {
    setBusy(true);
    try {
      await ticketApi.logWork(id, workLog);
      done('Work logged.', () => {
        setShowWorkLog(false);
        setWorkLog({ notes: '', parts_materials: '', labor_notes: '' });
      });
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doVerify = async () => {
    setBusy(true);
    try {
      await ticketApi.verify(id, { result: verifyResult, notes: verifyNotes || undefined });
      done(`Verification ${verifyResult}.`, () => {
        setShowVerify(false);
        setVerifyNotes('');
      });
    } catch (err) {
      setNotice(maintenanceErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const canTriage = hasPermission('maintenance.triage');
  const canWork = hasPermission('maintenance.work');
  const canApprove = hasPermission('maintenance.approve');

  if (loading) return <div className="mt-6"><Spinner /></div>;

  return (
    <PermissionGuard permission="maintenance.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/maintenance/tickets" className="text-sm text-gold hover:text-gold-light">← Tickets</Link>
          {error ? (
            <div className="mt-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
          ) : ticket && (
            <>
              <div className="mt-1 flex flex-wrap items-center gap-3">
                <h2 className="text-xl font-bold text-slate-100">{ticket.ticket_number}</h2>
                <StatusBadge value={ticket.status} />
                <span className="text-sm capitalize text-slate-400">{ticket.priority} priority</span>
                {ticket.breached && <span className="text-sm font-medium text-red-400">SLA breached</span>}
              </div>
              <p className="mt-1 text-sm text-slate-400">
                {ticket.property?.name}{ticket.unit ? ` · Unit ${ticket.unit.unit_number}` : ''} · {ticket.category}
              </p>
            </>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {ticket && (
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div className="space-y-5">
              <div className="aprms-card">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Ticket</h3>
                <p className="mt-2 text-sm text-slate-200">{ticket.description}</p>
                <dl className="mt-2 divide-y divide-charcoal-700/50">
                  <Row label="Reported by">{ticket.reported_by || '—'}</Row>
                  <Row label="Tenant">{ticket.tenant ? `${ticket.tenant.first_name} ${ticket.tenant.last_name}` : '—'}</Row>
                  <Row label="Assigned to">{ticket.assigned_to?.name || '—'}</Row>
                  <Row label="SLA due">{ticket.sla_due_at || '—'}</Row>
                </dl>
                {ticket.notes && <p className="mt-3 text-sm text-slate-400">{ticket.notes}</p>}

                <div className="mt-4 flex flex-wrap gap-2 border-t border-charcoal-700/60 pt-4">
                  {canTriage && (NEXT_ACTIONS[ticket.status] || []).map(([to, label]) => (
                    <button key={to} className="aprms-btn-ghost" disabled={busy} onClick={() => doTransition(to)}>
                      {label}
                    </button>
                  ))}
                  {canTriage && ['open', 'triaged'].includes(ticket.status) && !showAssign && (
                    <button className="aprms-btn-gold" onClick={() => setShowAssign(true)}>Assign Technician</button>
                  )}
                  {canWork && ticket.status === 'completed' && !showVerify && (
                    <span className="text-sm text-slate-500">Awaiting verification…</span>
                  )}
                </div>

                {showAssign && (
                  <div className="mt-3 flex gap-2">
                    <input
                      className="aprms-input" placeholder="Technician user ID"
                      value={technicianId} onChange={(e) => setTechnicianId(e.target.value)}
                    />
                    <button className="aprms-btn-gold" disabled={busy || !technicianId} onClick={doAssign}>
                      {busy ? '…' : 'Assign'}
                    </button>
                    <button className="aprms-btn-ghost" onClick={() => setShowAssign(false)}>Cancel</button>
                  </div>
                )}
              </div>

              <div className="aprms-card">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Quotes</h3>
                  {canWork && ['assigned', 'triaged'].includes(ticket.status) && !showQuote && (
                    <button className="aprms-btn-ghost !px-3 !py-1.5 !text-xs" onClick={() => setShowQuote(true)}>
                      + New Quote
                    </button>
                  )}
                </div>

                {showQuote && (
                  <div className="mt-3 space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Vendor</label>
                        <select className="aprms-input" value={vendorId} onChange={(e) => setVendorId(e.target.value)}>
                          <option value="">—</option>
                          {vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}
                        </select>
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Provider / Technician</label>
                        <input className="aprms-input" value={quote.provider} onChange={(e) => setQuote({ ...quote, provider: e.target.value })} placeholder="Name" />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Labor (PKR)</label>
                        <input type="number" min="0" step="0.01" className="aprms-input" value={quote.labor_cost} onChange={(e) => setQuote({ ...quote, labor_cost: e.target.value })} />
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Materials (PKR)</label>
                        <input type="number" min="0" step="0.01" className="aprms-input" value={quote.materials_cost} onChange={(e) => setQuote({ ...quote, materials_cost: e.target.value })} />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Cost borne by *</label>
                        <select className="aprms-input" value={quote.attribution} onChange={(e) => setQuote({ ...quote, attribution: e.target.value })}>
                          <option value="owner">Owner</option>
                          <option value="tenant">Tenant</option>
                        </select>
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Attribution reason *</label>
                        <input className="aprms-input" value={quote.attribution_reason} onChange={(e) => setQuote({ ...quote, attribution_reason: e.target.value })} placeholder="Why owner/tenant?" />
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <button className="aprms-btn-gold" disabled={busy || !quote.attribution_reason} onClick={doQuote}>
                        {busy ? 'Submitting…' : 'Submit for Approval'}
                      </button>
                      <button className="aprms-btn-ghost" onClick={() => setShowQuote(false)}>Cancel</button>
                    </div>
                  </div>
                )}

                <div className="mt-3 space-y-2">
                  {ticket.quotes.length === 0 && <p className="text-sm text-slate-500">No quotes yet.</p>}
                  {ticket.quotes.map((q) => (
                    <div key={q.id} className="rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-slate-200">
                          {q.vendor?.name || q.provider || 'Quote'}
                          <span className="ml-2 text-xs text-slate-500">
                            borne by {q.attribution} — {q.attribution_reason}
                          </span>
                        </span>
                        <span className="font-medium text-gold">{formatPKR(q.total)}</span>
                      </div>
                      <div className="mt-1 flex items-center justify-between">
                        <StatusBadge value={q.status} />
                        {canApprove && q.status === 'pending' && (
                          <div className="flex gap-2">
                            <button className="text-xs text-green-300 hover:text-green-200" disabled={busy} onClick={() => doDecideQuote(q.id, 'approved')}>Approve</button>
                            <button className="text-xs text-red-300 hover:text-red-200" disabled={busy} onClick={() => doDecideQuote(q.id, 'rejected')}>Reject</button>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="space-y-5">
              <div className="aprms-card">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Work Log</h3>
                  {canWork && ['approved', 'in_progress'].includes(ticket.status) && !showWorkLog && (
                    <button className="aprms-btn-ghost !px-3 !py-1.5 !text-xs" onClick={() => setShowWorkLog(true)}>
                      + Log Work
                    </button>
                  )}
                </div>

                {showWorkLog && (
                  <div className="mt-3 space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">What was done</label>
                      <textarea className="aprms-input" rows={2} value={workLog.notes} onChange={(e) => setWorkLog({ ...workLog, notes: e.target.value })} />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Parts / materials</label>
                      <input className="aprms-input" value={workLog.parts_materials} onChange={(e) => setWorkLog({ ...workLog, parts_materials: e.target.value })} />
                    </div>
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Labor notes</label>
                      <input className="aprms-input" value={workLog.labor_notes} onChange={(e) => setWorkLog({ ...workLog, labor_notes: e.target.value })} />
                    </div>
                    <div className="flex gap-2">
                      <button className="aprms-btn-gold" disabled={busy} onClick={doWorkLog}>
                        {busy ? 'Saving…' : 'Save Work Log'}
                      </button>
                      <button className="aprms-btn-ghost" onClick={() => setShowWorkLog(false)}>Cancel</button>
                    </div>
                  </div>
                )}

                <div className="mt-3 space-y-2">
                  {ticket.work_logs.length === 0 && <p className="text-sm text-slate-500">No work logged yet.</p>}
                  {ticket.work_logs.map((w) => (
                    <div key={w.id} className="rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                      <p className="text-sm text-slate-200">{w.notes || '—'}</p>
                      <p className="mt-1 text-xs text-slate-500">
                        {w.technician} · {w.started_at || ''}
                        {w.parts_materials ? ` · Parts: ${w.parts_materials}` : ''}
                      </p>
                    </div>
                  ))}
                </div>
              </div>

              <div className="aprms-card">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Verification</h3>
                {ticket.verification ? (
                  <dl className="mt-2 divide-y divide-charcoal-700/50">
                    <Row label="Result"><StatusBadge value={ticket.verification.result} /></Row>
                    <Row label="Verified by">{ticket.verification.verified_by}</Row>
                    <Row label="At">{ticket.verification.verified_at}</Row>
                    {ticket.verification.notes && <Row label="Notes">{ticket.verification.notes}</Row>}
                  </dl>
                ) : (
                  <p className="mt-2 text-sm text-slate-500">Not verified yet.</p>
                )}
                {canApprove && ticket.status === 'completed' && !showVerify && (
                  <button className="aprms-btn-gold mt-3" onClick={() => setShowVerify(true)}>
                    Verify Work
                  </button>
                )}
                {showVerify && (
                  <div className="mt-3 space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Result</label>
                      <select className="aprms-input" value={verifyResult} onChange={(e) => setVerifyResult(e.target.value)}>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed (send back for rework)</option>
                      </select>
                    </div>
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Notes</label>
                      <textarea className="aprms-input" rows={2} value={verifyNotes} onChange={(e) => setVerifyNotes(e.target.value)} />
                    </div>
                    <div className="flex gap-2">
                      <button className="aprms-btn-gold" disabled={busy} onClick={doVerify}>
                        {busy ? 'Saving…' : 'Submit Verification'}
                      </button>
                      <button className="aprms-btn-ghost" onClick={() => setShowVerify(false)}>Cancel</button>
                    </div>
                  </div>
                )}
              </div>

              {ticket.documents.length > 0 && (
                <div className="aprms-card">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Documents</h3>
                  <div className="mt-2 space-y-1">
                    {ticket.documents.map((d) => (
                      <p key={d.id} className="text-sm text-slate-300">📎 {d.name}</p>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        <ConfirmDialog
          open={confirmClose}
          title="Close this ticket?"
          message="The ticket must be verified before closure. Closed tickets are kept for history."
          confirmLabel="Close Ticket"
          busy={busy}
          onConfirm={doClose}
          onCancel={() => setConfirmClose(false)}
        />
      </div>
    </PermissionGuard>
  );
}
