import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import { depositApi, formatPKR, depositErrorMessage } from '../services/depositApi';

function Row({ label, children }) {
  return (
    <div className="flex flex-col gap-1 py-2 sm:flex-row sm:items-center">
      <dt className="w-44 shrink-0 text-xs font-medium uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-200">{children}</dd>
    </div>
  );
}

export default function DepositDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [deposit, setDeposit] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);

  // Receive form
  const [receiveAmount, setReceiveAmount] = useState('');
  const [showReceive, setShowReceive] = useState(false);

  // Deduction form
  const [showDeduction, setShowDeduction] = useState(false);
  const [deduction, setDeduction] = useState({
    category: 'damage', assessment: 'damage', description: '', amount: '', inspection_id: '',
  });

  // Settlement
  const [inspectionId, setInspectionId] = useState('');
  const [preview, setPreview] = useState(null);
  const [applyAmount, setApplyAmount] = useState('');
  const [confirmFinalize, setConfirmFinalize] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      setDeposit(await depositApi.get(id));
      setError('');
    } catch (err) {
      setError(depositErrorMessage(err, 'Could not load the deposit.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const refresh = async (msg) => {
    if (msg) setNotice(msg);
    setShowReceive(false);
    setShowDeduction(false);
    setReceiveAmount('');
    setDeduction({ category: 'damage', assessment: 'damage', description: '', amount: '', inspection_id: '' });
    await load();
  };

  const doReceive = async () => {
    setBusy(true);
    try {
      await depositApi.receive(id, { amount: parseFloat(receiveAmount), reason: 'Deposit received.' });
      refresh('Deposit received and recorded.');
    } catch (err) {
      setNotice(depositErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doProposeDeduction = async () => {
    setBusy(true);
    try {
      await depositApi.proposeDeduction(id, {
        ...deduction,
        amount: parseFloat(deduction.amount),
        inspection_id: deduction.inspection_id ? parseInt(deduction.inspection_id, 10) : undefined,
      });
      refresh('Deduction proposed — awaiting review.');
    } catch (err) {
      setNotice(depositErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doReviewDeduction = async (deductionId, decision) => {
    setBusy(true);
    try {
      await depositApi.reviewDeduction(deductionId, decision);
      refresh(`Deduction ${decision}.`);
    } catch (err) {
      setNotice(depositErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doDraft = async () => {
    setBusy(true);
    try {
      await depositApi.draftSettlement(id, parseInt(inspectionId, 10));
      refresh('Settlement drafted.');
    } catch (err) {
      setNotice(depositErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doPreview = async () => {
    setBusy(true);
    try {
      const p = await depositApi.previewSettlement(id, parseFloat(applyAmount || '0'));
      setPreview(p);
    } catch (err) {
      setNotice(depositErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const doFinalize = async () => {
    setBusy(true);
    try {
      await depositApi.finalizeSettlement(id, { apply_to_balance: parseFloat(applyAmount || '0') });
      setConfirmFinalize(false);
      setPreview(null);
      refresh('Settlement finalized and locked.');
    } catch (err) {
      setNotice(depositErrorMessage(err));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('deposits.manage');
  const canSettle = hasPermission('deposits.settle');

  if (loading) return <div className="mt-6"><Spinner /></div>;

  return (
    <PermissionGuard permission="deposits.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/deposits" className="text-sm text-gold hover:text-gold-light">← Deposits</Link>
          {error ? (
            <div className="mt-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
          ) : deposit && (
            <>
              <div className="mt-1 flex flex-wrap items-center gap-3">
                <h2 className="text-xl font-bold text-slate-100">
                  Deposit — {deposit.tenant?.name}
                </h2>
                <StatusBadge value={deposit.status} />
              </div>
              <p className="mt-1 text-sm text-slate-400">
                {deposit.property?.name}{deposit.unit ? ` · Unit ${deposit.unit.unit_number}` : ''}
              </p>
            </>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {deposit && (
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div className="space-y-5">
              <div className="aprms-card">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Deposit</h3>
                <dl className="mt-2 divide-y divide-charcoal-700/50">
                  <Row label="Agreed amount">{formatPKR(deposit.deposit_amount)}</Row>
                  <Row label="Held amount"><strong>{formatPKR(deposit.held_amount)}</strong></Row>
                  <Row label="Received">{deposit.received_date || '—'}</Row>
                  <Row label="Reference">{deposit.reference || '—'}</Row>
                </dl>
                {deposit.notes && <p className="mt-3 text-sm text-slate-400">{deposit.notes}</p>}

                {canManage && deposit.status === 'required' && !showReceive && (
                  <button className="aprms-btn-gold mt-4" onClick={() => setShowReceive(true)}>
                    Record Receipt
                  </button>
                )}
                {showReceive && (
                  <div className="mt-4 space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Amount received (PKR)</label>
                      <input
                        type="number" min="0.01" step="0.01" className="aprms-input"
                        value={receiveAmount} onChange={(e) => setReceiveAmount(e.target.value)}
                      />
                    </div>
                    <div className="flex gap-2">
                      <button className="aprms-btn-gold" disabled={busy || !receiveAmount} onClick={doReceive}>
                        {busy ? 'Saving…' : 'Confirm Receipt'}
                      </button>
                      <button className="aprms-btn-ghost" onClick={() => setShowReceive(false)}>Cancel</button>
                    </div>
                  </div>
                )}
              </div>

              <div className="aprms-card">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Transaction History</h3>
                <div className="mt-3 space-y-2">
                  {deposit.transactions.length === 0 && (
                    <p className="text-sm text-slate-500">No transactions yet.</p>
                  )}
                  {deposit.transactions.map((t) => (
                    <div key={t.id} className="rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-slate-200">
                          <StatusBadge value={t.type} />
                          <span className="ml-2 text-slate-400">{t.reason}</span>
                        </span>
                        <span className="font-medium text-gold">{formatPKR(t.amount)}</span>
                      </div>
                      <p className="mt-1 text-xs text-slate-500">
                        Balance after: {formatPKR(t.balance_after)} · {t.created_by || '—'} · {t.created_at}
                      </p>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="space-y-5">
              <div className="aprms-card">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Deductions</h3>
                  {canSettle && !deposit.settlement && (
                    <button className="aprms-btn-ghost !px-3 !py-1.5 !text-xs" onClick={() => setShowDeduction(!showDeduction)}>
                      + Propose
                    </button>
                  )}
                </div>

                {showDeduction && (
                  <div className="mt-3 space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Category</label>
                        <select className="aprms-input" value={deduction.category} onChange={(e) => setDeduction({ ...deduction, category: e.target.value })}>
                          <option value="damage">Damage</option>
                          <option value="cleaning">Cleaning</option>
                          <option value="unpaid_rent">Unpaid rent</option>
                          <option value="other">Other</option>
                        </select>
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Wear vs Damage</label>
                        <select className="aprms-input" value={deduction.assessment} onChange={(e) => setDeduction({ ...deduction, assessment: e.target.value })}>
                          <option value="damage">Chargeable damage</option>
                          <option value="wear">Normal wear (not charged)</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Description *</label>
                      <textarea className="aprms-input" rows={2} value={deduction.description} onChange={(e) => setDeduction({ ...deduction, description: e.target.value })} placeholder="What was found, where, and why it is chargeable…" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Amount (PKR) *</label>
                        <input type="number" min="0.01" step="0.01" className="aprms-input" value={deduction.amount} onChange={(e) => setDeduction({ ...deduction, amount: e.target.value })} />
                      </div>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Inspection ID</label>
                        <input className="aprms-input" value={deduction.inspection_id} onChange={(e) => setDeduction({ ...deduction, inspection_id: e.target.value })} placeholder="Optional" />
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <button className="aprms-btn-gold" disabled={busy || !deduction.description || !deduction.amount} onClick={doProposeDeduction}>
                        {busy ? 'Saving…' : 'Propose Deduction'}
                      </button>
                      <button className="aprms-btn-ghost" onClick={() => setShowDeduction(false)}>Cancel</button>
                    </div>
                  </div>
                )}

                <div className="mt-3 space-y-2">
                  {deposit.deductions.length === 0 && (
                    <p className="text-sm text-slate-500">No deductions proposed.</p>
                  )}
                  {deposit.deductions.map((d) => (
                    <div key={d.id} className="rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                      <div className="flex items-center justify-between text-sm">
                        <span className="text-slate-200">
                          {d.description}
                          <span className="ml-2 text-xs text-slate-500">
                            {d.category} · {d.assessment === 'wear' ? 'normal wear' : 'chargeable damage'}
                          </span>
                        </span>
                        <span className="font-medium text-gold">{formatPKR(d.amount)}</span>
                      </div>
                      <div className="mt-1 flex items-center justify-between">
                        <StatusBadge value={d.status} />
                        {canSettle && d.status === 'proposed' && (
                          <div className="flex gap-2">
                            <button className="text-xs text-green-300 hover:text-green-200" disabled={busy} onClick={() => doReviewDeduction(d.id, 'approved')}>Approve</button>
                            <button className="text-xs text-red-300 hover:text-red-200" disabled={busy} onClick={() => doReviewDeduction(d.id, 'rejected')}>Reject</button>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="aprms-card">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Final Settlement</h3>
                {!deposit.settlement ? (
                  canSettle && (
                    <div className="mt-3 space-y-3">
                      <p className="text-sm text-slate-500">
                        Settlement requires a reviewed move-out inspection.
                      </p>
                      <div>
                        <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">Inspection ID *</label>
                        <input className="aprms-input" value={inspectionId} onChange={(e) => setInspectionId(e.target.value)} placeholder="Reviewed inspection ID" />
                      </div>
                      <button className="aprms-btn-gold" disabled={busy || !inspectionId} onClick={doDraft}>
                        {busy ? 'Working…' : 'Draft Settlement'}
                      </button>
                    </div>
                  )
                ) : (
                  <div className="mt-3 space-y-3">
                    <dl className="divide-y divide-charcoal-700/50">
                      <Row label="Gross deposit">{formatPKR(deposit.settlement.gross_deposit)}</Row>
                      <Row label="Approved deductions">{formatPKR(deposit.settlement.total_deductions)}</Row>
                      <Row label="Applied to balance">{formatPKR(deposit.settlement.applied_to_balance)}</Row>
                      <Row label="Refund due"><strong className="text-gold">{formatPKR(deposit.settlement.refund_amount)}</strong></Row>
                      <Row label="Status"><StatusBadge value={deposit.settlement.status} /></Row>
                      {deposit.settlement.finalized_at && (
                        <Row label="Finalized">{deposit.settlement.finalized_at} by {deposit.settlement.approved_by}</Row>
                      )}
                    </dl>

                    {canSettle && deposit.settlement.status === 'draft' && (
                      <>
                        <div>
                          <label className="mb-1 block text-xs uppercase tracking-wider text-slate-400">
                            Apply to outstanding balance (PKR)
                          </label>
                          <input
                            type="number" min="0" step="0.01" className="aprms-input"
                            value={applyAmount} onChange={(e) => { setApplyAmount(e.target.value); setPreview(null); }}
                            placeholder="0.00"
                          />
                        </div>
                        <div className="flex gap-2">
                          <button className="aprms-btn-ghost" disabled={busy} onClick={doPreview}>
                            {busy ? 'Calculating…' : 'Preview Settlement'}
                          </button>
                          <button
                            className="aprms-btn-gold"
                            disabled={!preview || busy}
                            onClick={() => setConfirmFinalize(true)}
                            title={!preview ? 'Preview first' : ''}
                          >
                            Finalize
                          </button>
                        </div>

                        {preview && (
                          <div className="rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                            <p className="text-xs uppercase tracking-wider text-slate-500">Settlement preview</p>
                            <dl className="mt-2 space-y-1 text-sm">
                              <div className="flex justify-between"><span className="text-slate-400">Gross deposit</span><span>{formatPKR(preview.gross_deposit)}</span></div>
                              <div className="flex justify-between"><span className="text-slate-400">− Approved deductions</span><span className="text-red-300">{formatPKR(preview.total_deductions)}</span></div>
                              <div className="flex justify-between"><span className="text-slate-400">− Applied to balance</span><span className="text-red-300">{formatPKR(preview.applied_to_balance)}</span></div>
                              <div className="flex justify-between border-t border-charcoal-700 pt-1 font-medium"><span>= Refund due</span><span className="text-gold">{formatPKR(preview.refund_amount)}</span></div>
                            </dl>
                            <p className="mt-2 text-xs text-slate-500">
                              Tenant outstanding: {formatPKR(preview.tenant_outstanding)}. Finalizing locks the settlement.
                            </p>
                          </div>
                        )}
                      </>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        )}

        <ConfirmDialog
          open={confirmFinalize}
          title="Finalize this settlement?"
          message={preview
            ? `Gross ${formatPKR(preview.gross_deposit)} − deductions ${formatPKR(preview.total_deductions)} − applied ${formatPKR(preview.applied_to_balance)} = refund ${formatPKR(preview.refund_amount)}. This locks the settlement.`
            : 'Finalize the settlement?'}
          confirmLabel="Finalize Settlement"
          busy={busy}
          onConfirm={doFinalize}
          onCancel={() => setConfirmFinalize(false)}
        />
      </div>
    </PermissionGuard>
  );
}
