import React from 'react';
import {Link, useLocation} from 'react-router-dom';
import {FolderKanban, LayoutDashboard, LogOut, User} from 'lucide-react';
import {useAuth} from '@/context/AuthContext';
import {cn} from '@/lib/utils';

export default function AppNav() {
  const { logout } = useAuth();
  const { pathname } = useLocation();

  const links = [
    { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { to: '/profile', label: 'Profile', icon: User },
    // {/* >>> examples */}
    { to: '/projects', label: 'Projects', icon: FolderKanban },
    // {/* <<< examples */}
  ];

  return (
    <nav className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
      <div className="flex items-center gap-1">
        <span className="mr-4 text-lg font-bold text-brand">Gluo</span>
        {links.map(({ to, label, icon: Icon }) => (
          <Link
            key={to}
            to={to}
            className={cn(
              'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium',
              pathname === to ? 'bg-brand/10 text-brand' : 'text-slate-600 hover:bg-slate-100'
            )}
          >
            <Icon size={16} />
            {label}
          </Link>
        ))}
      </div>
      <button onClick={logout} className="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-800">
        <LogOut size={16} /> Logout
      </button>
    </nav>
  );
}
