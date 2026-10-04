import EmptyState from './EmptyState';

export default function Table({
  columns,
  rows,
  keyField = 'id',
  emptyTitle = 'No records yet',
  emptyMessage = 'There is nothing to show here.',
  loading = false,
}) {
  if (loading) {
    return (
      <div className="space-y-2" aria-label="Loading table">
        {[0, 1, 2].map((i) => (
          <div
            key={i}
            className="h-10 animate-pulse rounded-lg bg-charcoal-700"
          />
        ))}
      </div>
    );
  }

  if (!rows || rows.length === 0) {
    return <EmptyState title={emptyTitle} message={emptyMessage} />;
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-charcoal-700">
      <table className="min-w-full divide-y divide-charcoal-700 bg-charcoal-800 text-sm">
        <thead className="bg-charcoal-950/60">
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                className={`sticky top-0 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-400 ${
                  col.align === 'right' ? 'text-right' : 'text-left'
                }`}
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-charcoal-700">
          {rows.map((row) => (
            <tr key={row[keyField]} className="hover:bg-charcoal-700/50">
              {columns.map((col) => (
                <td
                  key={col.key}
                  className={`px-4 py-3 text-gray-200 ${
                    col.align === 'right' ? 'text-right tabular-nums' : ''
                  }`}
                >
                  {col.render ? col.render(row) : row[col.key]}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
