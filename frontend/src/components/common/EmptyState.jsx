/**
 * EmptyState — honest "no data yet" panels. P1 rule: never render fake KPIs.
 * Pass title + hint; optionally a CTA button.
 */
export default function EmptyState({ icon = '▢', title, hint, action }) {
  return (
    <div className="aprms-card flex flex-col items-center py-12 text-center">
      <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full border border-gold/30 bg-gold/10 text-2xl text-gold">
        {icon}
      </div>
      <p className="text-base font-semibold text-slate-100">{title}</p>
      {hint && <p className="mt-1 max-w-sm text-sm text-slate-400">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
