import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Topbar from './Topbar';

export default function AppShell() {
  const [navOpen, setNavOpen] = useState(false);

  return (
    <div className="flex min-h-screen bg-charcoal-950">
      <Sidebar open={navOpen} onClose={() => setNavOpen(false)} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar onMenu={() => setNavOpen(true)} />
        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          <Outlet />
        </main>
        <footer className="border-t border-charcoal-700/60 px-6 py-3 text-center text-[11px] text-slate-600">
          APRMS — Ahmed Property & Rental Management System · Developed by Ahmed
        </footer>
      </div>
    </div>
  );
}
