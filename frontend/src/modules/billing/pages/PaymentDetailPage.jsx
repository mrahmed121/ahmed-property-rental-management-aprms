import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import { paymentApi, receiptApi, formatPKR, billingErrorMessage } from '../services/billingApi';

function Row({ label, children }) {
  return (
    <div className="flex flex-col gap-1 py-2 sm:flex-row sm:items-center">
      <dt className="w-44 shrink-0 text-xs font-medium uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-200">{children}</dd>
    </div>
  );
}

export default function PaymentDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [payment, setPayment] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [confirmReverse, setConfirmReverse] = useState(false);
  const [reason, setReason] = useState('');
  const [showReasonForm, setShowReasonForm] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      setPayment(await paymentApi.get(id));
      setError('');
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load the payment.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const doReverse = async () => {
    setBusy(true);
    try {
      await paymentApi.reverse(id, reason);
      setNotice('Payment reversed. Allocations were rolled back and a reversal entry posted.');
      setConfirmReverse(false);
      setShowReasonForm(false);
      setReason('');
      load();
    } catch (err) {
      setNotice(billingErrorMessage(err, 'Could not reverse the payment.'));
    } finally {
      setBusy(false);
    }
  };

  const canReverse = hasPermission('payments.reverse');
  const canReceipt = hasPermission('receipts.view');

  if (loading) return <div className="mt-6"><Spinner /></div>;

  return (
    <PermissionGuard permission="payments.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/billing/payments" className="text-sm text-gold hover:text-gold-light">← Payments</Link>
          {error ? (
            <div className="mt-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
          ) : payment && (
            <>
              <div className="mt-1 flex flex-wrap items-center gap-3">
                <h2 className="text-xl font-bold text-slate-100">{payment.receipt_number}</h2>
                <StatusBadge value={payment.status} />
              </div>
              <p className="mt-1 text-sm text-slate-400">{payment.tenant?.name}</p>
            </>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {payment && (
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div className="aprms-card">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Payment</h3>
              <dl className="mt-2 divide-y divide-charcoal-700/50">
                <Row label="Amount"><strong>{formatPKR(payment.amount)}</strong></Row>
                <Row label="Date">{payment.payment_date}</Row>
                <Row label="Method">{payment.method.replace('_', ' ')}</Row>
                <Row label="Reference">{payment.reference || '—'}</Row>
                <Row label="Posted by">{payment.posted_by || '—'}</Row>
                {payment.lease && (
                  <Row label="Lease">
                    <Link to={`/leases/${payment.lease.id}`} className="text-gold hover:text-gold-light">
                      {payment.lease.lease_number}
                    </Link>
                  </Row>
                )}
              </dl>
              {payment.notes && <p className="mt-3 text-sm text-slate-400">{payment.notes}</p>}

              <div className="mt-4 flex flex-wrap gap-2 border-t border-charcoal-700/60 pt-4">
                {canReceipt && (
                  <a
                    href={receiptApi.pdfUrl(payment.id)}
                    className="aprms-btn-ghost"
                    target="_blank"
                    rel="noreferrer"
                  >
                    Download Receipt (PDF)
                  </a>
                )}
                {canReverse && payment.status === 'posted' && !showReasonForm && (
                  <button
                    className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2 text-sm font-medium text-red-300 hover:border-red-500/60"
                    onClick={() => setShowReasonForm(true)}
                  >
                    Reverse Payment
                  </button>
                )}
              </div>

              {showReasonForm && (
                <div className="mt-4 space-y-3 rounded-lg bg-charcoal-800 p-3 ring-1 ring-charcoal-700">
                  <div>
                    <label className="mb-1 block text-xs font-medium uppercase tracking-wider text-slate-400">
                      Reversal reason *
                    </label>
                    <textarea
                      className="aprms-input"
                      rows={2}
                      value={reason}
                      onChange={(e) => setReason(e.target.value)}
                      placeholder="Why is this payment being reversed?"
                    />
                  </div>
                  <div className="flex gap-2">
                    <button
                      className="inline-flex items-center rounded-lg bg-red-800 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                      disabled={!reason.trim()}
                      onClick={() => setConfirmReverse(true)}
                    >
                      Continue
                    </button>
                    <button className="aprms-btn-ghost" onClick={() => { setShowReasonForm(false); setReason(''); }}>
                      Cancel
                    </button>
                  </div>
                </div>
              )}
            </div>

            <div className="aprms-card">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
                Allocation (waterfall)
              </h3>
              <div className="mt-3 space-y-2">
                {payment.allocations.length === 0 && (
                  <p className="text-sm text-slate-500">Unallocated — held as tenant credit.</p>
                )}
                {payment.allocations.map((a) => (
                  <div key={a.id} className="flex items-center justify-between rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                    <span className="text-sm text-slate-200">
                      {a.target}
                      <span className="ml-2 text-xs text-slate-500">{a.bucket.replace('_', ' ')}</span>
                    </span>
                    <span className="text-sm font-medium text-gold">{formatPKR(a.amount)}</span>
                  </div>
                ))}
              </div>
              <dl className="mt-4 divide-y divide-charcoal-700/50 border-t border-charcoal-700/60 pt-2">
                <Row label="Allocated">{formatPKR(payment.allocated_total)}</Row>
                <Row label="Unallocated credit">{formatPKR(payment.unallocated)}</Row>
              </dl>
            </div>
          </div>
        )}

        <ConfirmDialog
          open={confirmReverse}
          title="Reverse this payment?"
          message="All allocations will be rolled back, invoice balances restored, and a reversal entry posted to the ledger. The payment record itself is kept for history."
          confirmLabel="Reverse Payment"
          busy={busy}
          onConfirm={doReverse}
          onCancel={() => setConfirmReverse(false)}
        />
      </div>
    </PermissionGuard>
  );
}
