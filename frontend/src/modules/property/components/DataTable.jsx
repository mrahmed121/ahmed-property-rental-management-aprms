import Spinner from '../../../components/common/Spinner';
import EmptyState from '../../../components/common/EmptyState';

/**
 * DataTable — paginated table with loading / error / empty states.
 * columns: [{ key, label, render?(row), className? }]
 */
export default function DataTable({
  columns,
  rows,
  meta,
  loading,
  error,
  emptyTitle,
  emptyHint,
  emptyAction,
  onPage,
  onRetry,
}) {
  if (loading) {
    return (
      <div className="aprms-card flex justify-center py-16">
        <Spinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="aprms-card py-12 text-center">
        <p className="text-base font-semibold text-red-300">Could not load data</p>
        <p className="mt-1 text-sm text-slate-400">{error}</p>
        {onRetry && (
          <button onClick={onRetry} className="aprms-btn-ghost mt-4">
            Retry
          </button>
        )}
      </div>
    );
  }

  if (!rows || rows.length === 0) {
    return <EmptyState icon="🏘️" title={emptyTitle} hint={emptyHint} action={emptyAction} />;
  }

  const totalPages = Math.max(1, Math.ceil((meta?.total || 0) / (meta?.per_page || 15)));

  return (
    <div className="overflow-hidden rounded-xl border border-charcoal-700/60 bg-charcoal-900/80">
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-left text-sm">
          <thead>
            <tr className="border-b border-charcoal-700/60 bg-charcoal-950/60">
              {columns.map((c) => (
                <th key={c.key} className={`px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-400 ${c.className || ''}`}>
                  {c.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b border-charcoal-800/60 last:border-0 hover:bg-charcoal-800/40">
                {columns.map((c) => (
                  <td key={c.key} className={`px-4 py-3 text-slate-200 ${c.className || ''}`}>
                    {c.render ? c.render(row) : row[c.key]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {meta && totalPages > 1 && (
        <div className="flex items-center justify-between border-t border-charcoal-700/60 px-4 py-3">
          <p className="text-xs text-slate-500">
            Page {meta.current_page} of {totalPages} · {meta.total} total
          </p>
          <div className="flex gap-2">
            <button
              className="aprms-btn-ghost !px-3 !py-1.5 !text-xs"
              disabled={meta.current_page <= 1}
              onClick={() => onPage(meta.current_page - 1)}
            >
              ← Prev
            </button>
            <button
              className="aprms-btn-ghost !px-3 !py-1.5 !text-xs"
              disabled={meta.current_page >= totalPages}
              onClick={() => onPage(meta.current_page + 1)}
            >
              Next →
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
