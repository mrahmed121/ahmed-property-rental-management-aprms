const variants = {
  primary:
    'bg-gold text-charcoal-950 hover:bg-gold-light disabled:opacity-50 disabled:cursor-not-allowed font-semibold',
  secondary:
    'bg-charcoal-700 text-gray-200 hover:bg-charcoal-600 disabled:opacity-50 disabled:cursor-not-allowed',
  danger:
    'bg-red-700 text-white hover:bg-red-600 disabled:opacity-50 disabled:cursor-not-allowed font-semibold',
  ghost: 'text-gold hover:bg-charcoal-800 disabled:opacity-50 disabled:cursor-not-allowed',
};

const sizes = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-sm',
  lg: 'px-5 py-2.5 text-base',
};

export default function Button({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  ...props
}) {
  return (
    <button
      className={`inline-flex items-center justify-center gap-2 rounded-lg transition-colors focus-gold ${variants[variant]} ${sizes[size]} ${className}`}
      {...props}
    >
      {children}
    </button>
  );
}
