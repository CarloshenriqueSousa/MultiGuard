/**
 * Cabecalho do console com status da rede, score de saude em tempo real, informacoes do operador e logout.
 */

import { Link, usePage } from '@inertiajs/react';
import { statusOf } from '../lib/format';

export default function Header({ unitName, health, evaluatedAt }) {
    const { auth } = usePage().props;
    const style = statusOf(health?.status);
    const age = evaluatedAt ? Math.round((Date.now() - Date.parse(evaluatedAt)) / 1000) : null;
    const stale = age !== null && age > 30;

    return (
        <header className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-800 bg-slate-900/80 p-4 shadow-xl backdrop-blur">
            <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-sky-500/30 bg-sky-500/10 text-sky-400 shadow-inner">
                    <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>

                <div>
                    <div className="flex items-center gap-2">
                        <h1 className="text-lg font-black tracking-wider text-slate-100">MULTI-GUARD</h1>
                        <span className="flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-400">
                            <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" /> Ao Vivo
                        </span>
                    </div>
                    <p className="text-xs text-slate-400">
                        Unidade: <span className="font-semibold text-slate-200">{unitName}</span>
                    </p>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-6">
                {!health && (
                    <span className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs font-medium text-amber-300">
                        Aguardando ciclo do avaliador (mg:evaluate)...
                    </span>
                )}

                {health && stale && (
                    <span className="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs font-medium text-amber-300">
                        Avaliador parado há {age}s
                    </span>
                )}

                {health && (
                    <div className="flex items-center gap-5 border-l border-slate-800 pl-5">
                        <div className="text-right">
                            <div className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Estado</div>
                            <div className={`text-sm font-bold ${style.text}`}>{style.label}</div>
                        </div>

                        <div className="text-right">
                            <div className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Índice de Saúde</div>
                            <div className={`text-3xl font-black ${style.text}`}>{health.score ?? '--'}</div>
                        </div>
                    </div>
                )}

                <div className="flex items-center gap-3 border-l border-slate-800 pl-5">
                    {auth?.user && (
                        <div className="hidden text-right sm:block">
                            <div className="text-xs font-semibold text-slate-200">{auth.user.name || 'Operador'}</div>
                            <div className="text-[10px] font-mono text-slate-500">{auth.user.email}</div>
                        </div>
                    )}

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="rounded-lg border border-slate-700/80 bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-300 shadow transition hover:border-rose-500/40 hover:bg-rose-500/10 hover:text-rose-300"
                    >
                        Sair
                    </Link>
                </div>
            </div>
        </header>
    );
}
