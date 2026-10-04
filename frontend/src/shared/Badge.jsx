const tones = {
  gold: 'bg-gold/15 text-gold border-gold/30',
  green: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
  red: 'bg-red-500/15 text-red-300 border-red-500/30',
  gray: 'bg-charcoal-600/40 text-gray-300 border-charcoal-600',
  copper: 'bg-copper/15 text-copper-light border-copper/30',
  blue: 'bg-blue-500/15 text-blue-300 border-blue-500/30',
};

export default function Badge({ children, tone = 'gray', className = '' }) {
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${tones[tone]} ${className}`}
    >
      {children}
    </span>
  );
}
