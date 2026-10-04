export default function Card({ children, className = '', title, action }) {
  return (
    <div
      className={`rounded-xl border border-charcoal-700 bg-charcoal-800 p-5 shadow-lg ${className}`}
    >
      {(title || action) && (
        <div className="mb-4 flex items-center justify-between">
          {title && (
            <h3 className="text-base font-semibold text-gray-100">{title}</h3>
          )}
          {action}
        </div>
      )}
      {children}
    </div>
  );
}
