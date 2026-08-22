import React from 'react';
import {Link} from 'react-router-dom';
import {BookOpen, FolderKanban, User} from 'lucide-react';
import {Card} from '@/components/ui';
import {useAuth} from '@/context/AuthContext';
import {BASE_URL} from '@/lib/api';

export default function Dashboard() {
  const { user } = useAuth();

  const cards = [
    { to: '/profile', label: 'Your Profile', desc: 'View and edit your account', icon: User },
    // {/* >>> examples */}
    { to: '/projects', label: 'Projects', desc: 'Browse the example CRUD', icon: FolderKanban },
    // {/* <<< examples */}
    { to: `${BASE_URL}/docs/`, label: 'API Docs', desc: 'Swagger UI', icon: BookOpen, external: true },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-800">Welcome{user?.name ? `, ${user.name}` : ''}</h1>
        <p className="text-slate-500">This is your Gluo full-stack starter dashboard.</p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {cards.map(({ to, label, desc, icon: Icon, external }) => {
          const inner = (
            <Card className="flex h-full items-start gap-3 p-5 transition hover:shadow-md">
              <div className="rounded-md bg-brand/10 p-2 text-brand">
                <Icon size={20} />
              </div>
              <div>
                <div className="font-semibold text-slate-800">{label}</div>
                <div className="text-sm text-slate-500">{desc}</div>
              </div>
            </Card>
          );
          return external ? (
            <a key={label} href={to} target="_blank" rel="noreferrer">{inner}</a>
          ) : (
            <Link key={label} to={to}>{inner}</Link>
          );
        })}
      </div>
    </div>
  );
}
