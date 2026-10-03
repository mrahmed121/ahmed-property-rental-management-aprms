import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import Spinner from '../../../components/common/Spinner';
import StatusBadge from '../../property/components/StatusBadge';
import ConfirmDialog from '../../property/components/ConfirmDialog';
import { invoiceApi, paymentApi, formatPKR, billingErrorMessage } from '../services/billingApi';

function Row({ label, children }) {
  return (
    <div className="flex flex-col gap-1 py-2 sm:flex-row sm:items-center">
      <dt className="w-44 shrink-0 text-xs font-medium uppercase tracking-wider text-slate-500">{label}</dt>
      <dd className="text-sm text-slate-200">{children}</dd>
    </div>
  );
}

export default function InvoiceDetailPage() {
  const { id } = useParams();
  const { hasPermission } = useAuth();
  const [invoice, setInvoice] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [confirmVoid, setConfirmVoid] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      setInvoice(await invoiceApi.get(id));
      setError('');
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load the invoice.'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [id]);

  const doVoid = async () => {
    setBusy(true);
    try {
      await invoiceApi.void(id);
      setNotice('Invoice voided. A reversal entry was posted to the ledger.');
      setConfirmVoid(false);
      load();
    } catch (err) {
      setNotice(billingErrorMessage(err, 'Could not void the invoice.'));
    } finally {
      setBusy(false);
    }
  };

  const canGenerate = hasPermission('invoices.generate');
  const canRecord = hasPermission('payments.record');

  if (loading) return <div className="mt-6"><Spinner /></div>;

  return (
    <PermissionGuard permission="invoices.view" showForbidden>
      <div className="space-y-5">
        <div>
          <Link to="/billing/invoices" className="text-sm text-gold hover:text-gold-light">← Invoices</Link>
          {error ? (
            <div className="mt-4 rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-3 text-sm text-red-300">{error}</div>
          ) : invoice && (
            <>
              <div className="mt-1 flex flex-wrap items-center gap-3">
                <h2 className="text-xl font-bold text-slate-100">{invoice.invoice_number}</h2>
                <StatusBadge value={invoice.status} />
              </div>
              <p className="mt-1 text-sm text-slate-400">
                {invoice.tenant?.name} · {invoice.property?.name}
                {invoice.unit ? ` · Unit ${invoice.unit.unit_number}` : ''}
              </p>
            </>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        {invoice && (
          <div className="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <div className="aprms-card">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Invoice</h3>
              <dl className="mt-2 divide-y divide-charcoal-700/50">
                <Row label="Period">{invoice.period_start} → {invoice.period_end}</Row>
                <Row label="Issue date">{invoice.issue_date}</Row>
                <Row label="Due date">{invoice.due_date}</Row>
                <Row label="Base rent">{formatPKR(invoice.base_rent)}</Row>
                {invoice.utilities > 0 && <Row label="Utilities">{formatPKR(invoice.utilities)}</Row>}
                {invoice.other_charges > 0 && <Row label="Other charges">{formatPKR(invoice.other_charges)}</Row>}
                {invoice.late_fee > 0 && <Row label="Late fee">{formatPKR(invoice.late_fee)}</Row>}
                <Row label="Total"><strong>{formatPKR(invoice.total)}</strong></Row>
                <Row label="Paid">{formatPKR(invoice.paid_amount)}</Row>
                <Row label="Outstanding">
                  <strong className={invoice.outstanding > 0 ? 'text-red-300' : 'text-slate-400'}>
                    {formatPKR(invoice.outstanding)}
                  </strong>
                </Row>
                {invoice.lease && (
                  <Row label="Lease">
                    <Link to={`/leases/${invoice.lease_id}`} className="text-gold hover:text-gold-light">
                      {invoice.lease.lease_number}
                    </Link>
                  </Row>
                )}
              </dl>
              {invoice.notes && <p className="mt-3 text-sm text-slate-400">{invoice.notes}</p>}

              <div className="mt-4 flex flex-wrap gap-2 border-t border-charcoal-700/60 pt-4">
                {canRecord && invoice.outstanding > 0 && (
                  <Link to={`/billing/payments/new?tenant_id=${invoice.tenant?.id}`} className="aprms-btn-gold">
                    Record Payment
                  </Link>
                )}
                {canGenerate && ['issued', 'partially_paid'].includes(invoice.status) && invoice.paid_amount === 0 && (
                  <button
                    className="inline-flex items-center rounded-lg border border-red-900/60 bg-red-950/40 px-4 py-2 text-sm font-medium text-red-300 hover:border-red-500/60"
                    onClick={() => setConfirmVoid(true)}
                  >
                    Void Invoice
                  </button>
                )}
              </div>
            </div>

            <div className="space-y-5">
              {invoice.late_fee_record && (
                <div className="aprms-card">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Late Fee</h3>
                  <dl className="mt-2 divide-y divide-charcoal-700/50">
                    <Row label="Amount">{formatPKR(invoice.late_fee_record.amount)}</Row>
                    <Row label="Status"><StatusBadge value={invoice.late_fee_record.status} /></Row>
                  </dl>
                </div>
              )}

              {invoice.dunning && invoice.dunning.length > 0 && (
                <div className="aprms-card">
                  <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">Reminders</h3>
                  <div className="mt-2 space-y-2">
                    {invoice.dunning.map((d) => (
                      <div key={d.id} className="flex items-center justify-between rounded-lg bg-charcoal-800 px-3 py-2 ring-1 ring-charcoal-700">
                        <span className="text-sm text-slate-300">{d.stage.replace('_', ' ')}</span>
                        <StatusBadge value={d.status} />
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        <ConfirmDialog
          open={confirmVoid}
          title="Void this invoice?"
          message="The invoice will be marked void and a reversal entry posted to the tenant ledger. This cannot be undone — voided invoices stay in history."
          confirmLabel="Void Invoice"
          busy={busy}
          onConfirm={doVoid}
          onCancel={() => setConfirmVoid(false)}
        />
      </div>
    </PermissionGuard>
  );
}
