/**
 * Grade de monitoramento com o estado em tempo real de cada dispositivo e enlace da topologia.
 */

import { LAYERS, statusOf } from '../lib/format';

function DeviceIcon({ kind, className = 'h-4 w-4' }) {
    if (kind === 'router') {
        return (
            <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 3v18M3 12h18M7.5 7.5l9 9M16.5 7.5l-9 9" />
            </svg>
        );
    }

    if (kind === 'server') {
        return (
            <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="2" width="20" height="8" rx="2" />
                <rect x="2" y="14" width="20" height="8" rx="2" />
                <circle cx="6" cy="6" r="1" fill="currentColor" />
                <circle cx="6" cy="18" r="1" fill="currentColor" />
            </svg>
        );
    }

    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <rect x="2" y="5" width="20" height="14" rx="2" />
            <path d="M6 12h2M10 12h2M14 12h2M18 12h2" />
        </svg>
    );
}

function Card({ title, subtitle, iconKind, row, active, onClick }) {
    const style = statusOf(row?.status);

    return (
        <button
            type="button"
            onClick={onClick}
            className={`group relative flex flex-col justify-between rounded-xl border p-3 text-left transition-all duration-200 hover:scale-[1.02] hover:border-slate-600 ${
                active
                    ? 'border-sky-500 bg-sky-950/40 ring-2 ring-sky-500/40 shadow-lg shadow-sky-500/10'
                    : 'border-slate-800 bg-slate-950/60 hover:bg-slate-900/60'
            }`}
        >
            <div className="flex items-center justify-between gap-1.5 w-full">
                <div className="flex items-center gap-2 overflow-hidden">
                    {iconKind && (
                        <div className={`text-slate-400 group-hover:text-slate-200 ${active ? 'text-sky-400' : ''}`}>
                            <DeviceIcon kind={iconKind} />
                        </div>
                    )}
                    <span className="truncate text-xs font-bold text-slate-100">{title}</span>
                </div>
                <span className={`h-2 w-2 flex-shrink-0 rounded-full ${style.dot} ${style.dot.includes('emerald') ? '' : 'animate-ping'}`} />
            </div>

            <div className="mt-2.5 flex items-baseline justify-between w-full">
                <span className="truncate font-mono text-[11px] text-slate-400">{subtitle}</span>
                <span className={`text-xs font-black ${style.text}`}>{row?.score ?? '--'}</span>
            </div>
        </button>
    );
}

export default function StatusGrid({ nodes, edges, health, selected, onSelect }) {
    const deviceRows = new Map((health?.devices ?? []).map((row) => [Number(row.device_id), row]));
    const linkRows = new Map((health?.links ?? []).map((row) => [Number(row.link_id), row]));
    const names = new Map(nodes.map((node) => [node.id, node.name]));

    return (
        <section className="rounded-xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl backdrop-blur">
            <div className="flex items-center justify-between border-b border-slate-800/80 pb-3">
                <div className="flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-sky-400" />
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-200">
                        Estado da Rede por Camada
                    </h2>
                </div>
                <span className="text-xs text-slate-500">
                    Clique em um módulo para inspecionar métricas
                </span>
            </div>

            <div className="mt-4 space-y-5">
                {LAYERS.map((layer) => {
                    const members = nodes.filter((node) => node.layer === layer.id);

                    if (members.length === 0) {
                        return null;
                    }

                    return (
                        <div key={layer.id}>
                            <div className="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                {layer.label}
                            </div>
                            <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                                {members.map((node) => (
                                    <Card
                                        key={node.id}
                                        title={node.name}
                                        subtitle={node.ip}
                                        iconKind={node.kind}
                                        row={deviceRows.get(node.id)}
                                        active={selected === `device:${node.id}`}
                                        onClick={() => onSelect(`device:${node.id}`)}
                                    />
                                ))}
                            </div>
                        </div>
                    );
                })}

                <div>
                    <div className="mb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        Enlaces de Comunicação
                    </div>
                    <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                        {edges.map((edge) => (
                            <Card
                                key={edge.id}
                                title={`${names.get(edge.from_device_id)} → ${names.get(edge.to_device_id)}`}
                                subtitle={`${Math.round(edge.capacity_bps / 1e9)} Gbps`}
                                row={linkRows.get(edge.id)}
                                active={selected === `link:${edge.id}`}
                                onClick={() => onSelect(`link:${edge.id}`)}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
