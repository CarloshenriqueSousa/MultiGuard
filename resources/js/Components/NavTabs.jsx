/**
 * Abas de navegacao entre as paginas do console (painel e mapa 3D). Usa o Link do Inertia, que troca de
 * pagina sem recarregar o navegador. A aba da pagina atual vem destacada pela propriedade current.
 */

import { Link } from '@inertiajs/react';

const TABS = [
    { id: 'dashboard', label: 'Console', href: '/dashboard' },
    { id: 'map', label: 'Mapa 3D', href: '/map' },
];

export default function NavTabs({ current }) {
    return (
        <nav className="flex gap-2">
            {TABS.map((tab) => (
                <Link
                    key={tab.id}
                    href={tab.href}
                    className={`rounded px-3 py-1.5 text-sm ${
                        current === tab.id ? 'bg-slate-800 text-slate-100' : 'text-slate-400 hover:text-slate-200'
                    }`}
                >
                    {tab.label}
                </Link>
            ))}
        </nav>
    );
}
