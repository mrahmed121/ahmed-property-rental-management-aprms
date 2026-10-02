export default function Spinner({ label = 'Loading…' }) {
  return (
    <div className="flex flex-col items-center gap-3" role="status" aria-live="polite">
      <div className="h-9 w-9 animate-spin rounded-full border-2 border-charcoal-700 border-t-gold" />
      <span className="text-sm text-slate-400">{label}</span>
    </div>
  );
}
