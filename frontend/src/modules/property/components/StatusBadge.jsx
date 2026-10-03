const STYLES = {
  // property / building
  active: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
  inactive: 'bg-slate-500/15 text-slate-300 ring-slate-500/40',
  // unit lifecycle
  vacant: 'bg-sky-500/15 text-sky-300 ring-sky-500/40',
  occupied: 'bg-emerald-500/15 text-emerald-300 ring-emerald-500/40',
  reserved: 'bg-amber-500/15 text-amber-300 ring-amber-500/40',
  maintenance: 'bg-orange-500/15 text-orange-300 ring-orange-500/40',
  // property types
  residential: 'bg-gold/15 text-gold ring-gold/40',
  commercial: 'bg-copper/15 text-copper-light ring-copper/40',
  'mixed-use': 'bg-violet-500/15 text-violet-300 ring-violet-500/40',
};

export default function StatusBadge({ value }) {
  const style = STYLES[value] || 'bg-slate-500/15 text-slate-300 ring-slate-500/40';
  const label = String(value || '').replace(/-/g, ' ');
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ${style}`}
    >
      {label}
    </span>
  );
}
