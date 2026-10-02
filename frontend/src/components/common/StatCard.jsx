export default function StatCard({ label, icon = '◆' }) {
  // P1: values are intentionally absent until P2+ modules provide query-backed data.
  return (
    <div className="aprms-card">
      <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
        <span className="text-gold">{icon}</span>
        {label}
      </div>
      <p className="mt-3 text-sm text-slate-500">No data yet</p>
    </div>
  );
}
