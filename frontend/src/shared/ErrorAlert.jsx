export default function ErrorAlert({ message, onRetry, className = '' }) {
  if (!message) return null;
  return (
    <div
      role="alert"
      className={`flex items-start justify-between gap-4 rounded-lg border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300 ${className}`}
    >
      <span>{message}</span>
      {onRetry && (
        <button
          onClick={onRetry}
          className="shrink-0 font-semibold text-red-200 underline hover:text-red-100"
        >
          Retry
        </button>
      )}
    </div>
  );
}
