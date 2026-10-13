/**
 * Painel de incidentes recentes com causa raiz, severidade, evidencia e raio de impacto.
 */

import { CAUSE, formatDuration, formatTime } from '../lib/format';

const SEVERITY = {
    critical: 'border-rose-500/30 bg-rose-500/15 text-rose-400',
    warning: 'border-amber-500/30 bg-amber-500/15 text-amber-400',
};

export default function IncidentList({ incidents, nodes }) {
    const names = new Map(nodes.map((node) => [node.id, node.name]));
    const now = Math.floor(Date.now() / 1000);

    return (
        <section className="rounded-xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl backdrop-blur">
            <div className="flex items-center justify-between border-b border-slate-800/80 pb-3">
                <div className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-rose-400" />
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-200">
                        Incidentes (Última Hora)
                    </h2>
                </div>
                <span className="rounded-full bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-400">
                    {incidents.length}
                </span>
            </div>

            {incidents.length === 0 && (
                <div className="mt-4 rounded-lg border border-slate-800/80 bg-slate-950/40 p-4 text-center">
                    <span className="text-emerald-400 text-lg">✓</span>
                    <p className="mt-1 text-xs font-medium text-slate-300">Nenhum incidente registrado</p>
                    <p className="text-[11px] text-slate-500">A rede operou com estabilidade na última hora.</p>
                </div>
            )}

            <ul className="mt-4 space-y-3">
                {incidents.map((incident) => {
                    const open = incident.status === 'open';
                    const duration = (open ? now : incident.ended_ts) - incident.started_ts;
                    const affected = (incident.affected_device_ids ?? []).map((id) => names.get(id)).filter(Boolean);
                    const evidence = Object.entries(incident.evidence ?? {})
                        .map(([key, value]) => `${key}: ${String(value)}`)
                        .join(' · ');

                    return (
                        <li key={incident.id} className="rounded-xl border border-slate-800 bg-slate-950/60 p-3.5 transition hover:border-slate-700">
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-xs font-bold text-slate-100">
                                    {CAUSE[incident.cause] ?? incident.cause}
                                </span>
                                <span className={`rounded-full border px-2 py-0.5 text-[9px] font-extrabold uppercase ${SEVERITY[incident.severity] ?? SEVERITY.warning}`}>
                                    {open ? 'em aberto' : 'resolvido'}
                                </span>
                            </div>

                            <div className="mt-2 text-xs text-slate-400">
                                Raiz: <span className="font-semibold text-slate-200">{incident.root_device ?? 'unidade'}</span>
                                {' · '}início {formatTime(incident.started_ts)}
                                {' · '}duração {formatDuration(duration)}
                            </div>

                            {affected.length > 0 && (
                                <div className="mt-1.5 text-[11px] text-slate-400">
                                    Afetados: <span className="text-slate-300">{affected.join(', ')}</span>
                                </div>
                            )}

                            {evidence && (
                                <div className="mt-2 rounded bg-slate-900/90 px-2 py-1 font-mono text-[10px] text-slate-400">
                                    {evidence}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
