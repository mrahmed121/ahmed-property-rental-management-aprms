export default function EmptyState({
  title = 'Nothing here yet',
  message = 'There is no data to display.',
  action = null,
}) {
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-charcoal-600 bg-charcoal-800/50 px-6 py-12 text-center">
      <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-charcoal-700">
        <svg
          className="h-6 w-6 text-gold"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          strokeWidth={1.5}
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
          />
        </svg>
      </div>
      <h3 className="text-sm font-semibold text-gray-200">{title}</h3>
      <p className="mt-1 max-w-sm text-sm text-gray-500">{message}</p>
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
