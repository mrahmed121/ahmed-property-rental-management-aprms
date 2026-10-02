import { useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

export default function Topbar({ onMenu }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <header className="flex items-center justify-between border-b border-charcoal-700/60 bg-charcoal-900/80 px-4 py-3 backdrop-blur lg:px-6">
      <div className="flex items-center gap-3">
        <button
          onClick={onMenu}
          className="rounded-lg border border-charcoal-700 p-2 text-slate-300 hover:text-gold lg:hidden"
          aria-label="Open menu"
        >
          ☰
        </button>
        <div>
          <h1 className="text-base font-semibold text-slate-100">
            {user?.agency?.name || 'APRMS'}
          </h1>
          <p className="text-xs text-slate-500">Ahmed — Own Every Square Foot.</p>
        </div>
      </div>
      <div className="flex items-center gap-3">
        <span className="hidden rounded-full border border-gold/30 bg-gold/10 px-3 py-1 text-xs font-medium text-gold sm:inline">
          {(user?.roles || [])[0] || '—'}
        </span>
        <button onClick={handleLogout} className="aprms-btn-ghost !px-3 !py-1.5 text-xs">
          Logout
        </button>
      </div>
    </header>
  );
}
