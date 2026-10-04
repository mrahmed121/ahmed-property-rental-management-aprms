export default function Input({
  label,
  error,
  hint,
  className = '',
  id,
  ...props
}) {
  const inputId = id || props.name;
  return (
    <div className={className}>
      {label && (
        <label
          htmlFor={inputId}
          className="mb-1.5 block text-sm font-medium text-gray-300"
        >
          {label}
        </label>
      )}
      <input
        id={inputId}
        className={`w-full rounded-lg border bg-charcoal-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus-gold ${
          error ? 'border-red-500' : 'border-charcoal-600'
        }`}
        {...props}
      />
      {error && <p className="mt-1 text-xs text-red-400">{error}</p>}
      {!error && hint && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
    </div>
  );
}
