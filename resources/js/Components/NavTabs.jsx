/**
 * Abas de navegacao entre o console principal e o mapa de topologia da rede via Inertia Link.
 */

import { Link } from '@inertiajs/react';

const TABS = [
    { id: 'dashboard', label: 'Console', href: '/dashboard' },
    { id: 'map', label: 'Mapa de Rede', href: '/map' },
];

export default function NavTabs({ current }) {
    return (
        <nav className="flex gap-2">
            {TABS.map((tab) => (
                <Link
                    key={tab.id}
                    href={tab.href}
                    className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${
                        current === tab.id ? 'bg-slate-800 text-sky-400 shadow-sm border border-slate-700/60' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-850'
                    }`}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
