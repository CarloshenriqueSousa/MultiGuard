/**
 * Lista os incidentes da ultima hora, abertos primeiro na ordem devolvida pela API (mais recentes no topo).
 * Cada item mostra a causa provavel apontada pelo motor, o dispositivo raiz, os dispositivos afetados (o raio
 * de impacto), a duracao e a evidencia usada na decisao. Incidente aberto tem a duracao contada ate agora; o
 * encerrado, ate a ultima evidencia registrada.
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
        <section className="rounded-lg border border-slate-800 bg-slate-900 p-4">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-300">Incidentes (última hora)</h2>

            {incidents.length === 0 && <p className="mt-4 text-sm text-slate-500">Nenhum incidente na última hora.</p>}

            <ul className="mt-4 space-y-3">
                {incidents.map((incident) => {
                    const open = incident.status === 'open';
                    const duration = (open ? now : incident.ended_ts) - incident.started_ts;
                    const affected = (incident.affected_device_ids ?? []).map((id) => names.get(id)).filter(Boolean);
                    const evidence = Object.entries(incident.evidence ?? {})
                        .map(([key, value]) => `${key}: ${String(value)}`)
                        .join(' · ');

                    return (
                        <li key={incident.id} className="rounded border border-slate-800 bg-slate-950/50 p-3">
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-semibold text-slate-100">{CAUSE[incident.cause] ?? incident.cause}</span>
                                <span className={`rounded border px-2 py-0.5 text-[10px] font-bold uppercase ${SEVERITY[incident.severity] ?? SEVERITY.warning}`}>
                                    {open ? 'aberto' : 'resolvido'}
                                </span>
                            </div>

                            <div className="mt-1 text-xs text-slate-400">
                                Raiz: <span className="text-slate-200">{incident.root_device ?? 'unidade'}</span>
                                {' · '}início {formatTime(incident.started_ts)}
                                {' · '}duração {formatDuration(duration)}
                            </div>

                            {affected.length > 0 && (
                                <div className="mt-1 text-xs text-slate-500">Afetados: {affected.join(', ')}</div>
                            )}

                            {evidence && <div className="mt-1 font-mono text-[11px] text-slate-600">{evidence}</div>}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
