import { useCallback, useEffect, useState } from 'react';
import {useSearchParams } from 'react-router-dom';
import { useAuth } from '../../../context/AuthContext';
import PermissionGuard from '../../../components/common/PermissionGuard';
import DataTable from '../../property/components/DataTable';
import StatusBadge from '../../property/components/StatusBadge';
import { dunningApi, billingErrorMessage } from '../services/billingApi';

const STAGES = ['', 'day_3', 'day_7', 'day_15', 'day_30'];

export default function DunningPage() {
  const { hasPermission } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);

  const filters = {
    stage: searchParams.get('stage') || '',
    page: parseInt(searchParams.get('page') || '1', 10),
  };

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const { items, meta } = await dunningApi.list({
        per_page: 12,
        stage: filters.stage || undefined,
        page: filters.page,
      });
      setRows(items);
      setMeta(meta);
    } catch (err) {
      setError(billingErrorMessage(err, 'Could not load reminders.'));
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

  const doProcess = async () => {
    setBusy(true);
    setNotice('');
    try {
      const r = await dunningApi.process();
      setNotice(r.message);
      load();
    } catch (err) {
      setNotice(billingErrorMessage(err, 'Could not process dunning.'));
    } finally {
      setBusy(false);
    }
  };

  const doMarkSent = async (id) => {
    setBusy(true);
    try {
      await dunningApi.markSent(id);
      setNotice('Reminder marked as sent.');
      load();
    } catch (err) {
      setNotice(billingErrorMessage(err, 'Could not update the reminder.'));
    } finally {
      setBusy(false);
    }
  };

  const canManage = hasPermission('dunning.manage');

  return (
    <PermissionGuard permission="dunning.view" showForbidden>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-xl font-bold text-slate-100">Dunning</h2>
            <p className="mt-1 text-sm text-slate-400">
              Reminder cadence: day 3 → 7 → 15 → 30 after the due date. System channel — no external provider is configured, so reminders are recorded as system events, not claimed deliveries.
            </p>
          </div>
          {canManage && (
            <button className="aprms-btn-gold" disabled={busy} onClick={doProcess}>
              {busy ? 'Processing…' : 'Process Overdue'}
            </button>
          )}
        </div>

        {notice && (
          <div className="rounded-lg border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-gold-light">{notice}</div>
        )}

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <select
            className="aprms-input"
            value={filters.stage}
            onChange={(e) => setFilter({ stage: e.target.value })}
          >
            {STAGES.map((s) => (
              <option key={s} value={s}>{s ? s.replace('_', ' ') : 'All stages'}</option>
            ))}
          </select>
        </div>

        <DataTable
          columns={[
            { key: 'tenant', label: 'Tenant', render: (r) => r.tenant || '—' },
            { key: 'invoice', label: 'Invoice', render: (r) => <span className="text-slate-300">{r.invoice_number}</span> },
            { key: 'stage', label: 'Stage', render: (r) => <StatusBadge value={r.stage} /> },
            { key: 'status', label: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            { key: 'scheduled', label: 'Scheduled', render: (r) => <span className="text-slate-400">{r.scheduled_at}</span> },
            {
              key: 'actions',
              label: '',
              className: 'text-right',
              render: (r) => (
                canManage && r.status === 'pending' ? (
                  <button
                    className="aprms-btn-ghost !px-3 !py-1.5 !text-xs"
                    disabled={busy}
                    onClick={() => doMarkSent(r.id)}
                  >
                    Mark Sent
                  </button>
                ) : null
              ),
            },
          ]}
          rows={rows}
          meta={meta}
          loading={loading}
          error={error}
          emptyTitle="No reminders"
          emptyHint="Run “Process Overdue” to schedule reminders for invoices past their due dates."
          onPage={(page) => setFilter({ page })}
        />
      </div>
    </PermissionGuard>
  );
}
