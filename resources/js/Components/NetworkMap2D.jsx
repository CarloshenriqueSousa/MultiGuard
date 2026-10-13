/**
 * Mapa topologico 2D interativo da rede em SVG com organizacao hierarquica por camadas e realce de parentesco.
 */

import { useMemo } from 'react';
import { statusOf } from '../lib/format';

const LAYER_ORDER = { gateway: 0, core: 1, server: 1, distribution: 2, access: 3 };
const LAYER_LABELS = { gateway: 'Gateway (Borda)', core: 'Núcleo (Core)', server: 'Servidores', distribution: 'Distribuição', access: 'Acesso' };

function DeviceIcon({ kind, className = 'h-5 w-5' }) {
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

export default function NetworkMap2D({ topology, health, latest, focus, selectedId, onSelectNode }) {
    const nodes = topology?.nodes ?? [];
    const edges = topology?.edges ?? [];

    const { parentIds, childIds, connectedEdgeIds } = useMemo(() => {
        if (!selectedId) {
            return { parentIds: new Set(), childIds: new Set(), connectedEdgeIds: new Set() };
        }

        const parents = new Set();
        const children = new Set();
        const connectedEdges = new Set();

        edges.forEach((edge) => {
            if (edge.to_device_id === selectedId) {
                parents.add(edge.from_device_id);
                connectedEdges.add(edge.id);
            }
            if (edge.from_device_id === selectedId) {
                children.add(edge.to_device_id);
                connectedEdges.add(edge.id);
            }
        });

        return { parentIds: parents, childIds: children, connectedEdgeIds: connectedEdges };
    }, [selectedId, edges]);

    const nodePositions = useMemo(() => {
        const positions = new Map();
        const width = 940;
        const height = 480;

        const fixedCoordinates = {
            1: { x: width / 2, y: 55 },
            2: { x: width / 2 - 120, y: 165 },
            3: { x: width / 2 + 200, y: 165 },
            4: { x: width / 2 - 240, y: 285 },
            5: { x: width / 2 + 120, y: 285 },
            6: { x: width / 2 - 340, y: 415 },
            7: { x: width / 2 - 140, y: 415 },
            8: { x: width / 2 + 20, y: 415 },
            9: { x: width / 2 + 220, y: 415 },
        };

        nodes.forEach((node, index) => {
            if (fixedCoordinates[node.id]) {
                positions.set(node.id, fixedCoordinates[node.id]);
            } else {
                const layerIndex = LAYER_ORDER[node.layer] ?? 2;
                const layerY = 55 + layerIndex * 120;
                positions.set(node.id, { x: 120 + index * 90, y: layerY });
            }
        });

        return positions;
    }, [nodes]);

    return (
        <div className="relative w-full rounded-xl border border-slate-800 bg-slate-900/90 p-4 shadow-xl backdrop-blur overflow-hidden">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-800/80 pb-3">
                <div className="flex items-center gap-2">
                    <span className="flex h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse" />
                    <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-200">
                        Topologia da Rede em Tempo Real
                    </h2>
                </div>

                <div className="flex items-center gap-3 text-xs">
                    {selectedId ? (
                        <button
                            type="button"
                            onClick={() => onSelectNode(null)}
                            className="rounded-lg border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-sky-300 transition hover:bg-sky-500/20"
                        >
                            Limpar Foco (Esc)
                        </button>
                    ) : (
                        <span className="text-slate-400">Clique num dispositivo para ver seus parentes e telemetria</span>
                    )}
                </div>
            </div>

            <div className="relative w-full overflow-x-auto">
                <svg
                    viewBox="0 0 940 480"
                    className="w-full min-w-[760px] h-auto select-none"
                    style={{ minHeight: '440px' }}
                >
                    <defs>
                        <linearGradient id="edge-default" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stopColor="#38bdf8" stopOpacity="0.4" />
                            <stop offset="100%" stopColor="#38bdf8" stopOpacity="0.2" />
                        </linearGradient>
                        <linearGradient id="edge-highlight" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stopColor="#38bdf8" stopOpacity="1" />
                            <stop offset="100%" stopColor="#818cf8" stopOpacity="1" />
                        </linearGradient>
                    </defs>

                    <g className="edges">
                        {edges.map((edge) => {
                            const fromPos = nodePositions.get(edge.from_device_id);
                            const toPos = nodePositions.get(edge.to_device_id);

                            if (!fromPos || !toPos) {
                                return null;
                            }

                            const isConnected = connectedEdgeIds.has(edge.id);
                            const isDimmed = selectedId !== null && !isConnected;

                            const linkHealth = (health?.links ?? []).find((l) => Number(l.link_id) === edge.id);
                            const linkValues = latest?.links?.[edge.id] ?? {};
                            const utilPct = linkValues.if_util_pct ?? 0;
                            const isDown = linkHealth?.status === 'critical' || linkHealth?.status === 'down';

                            const x1 = fromPos.x;
                            const y1 = fromPos.y + 24;
                            const x2 = toPos.x;
                            const y2 = toPos.y - 24;
                            const cy = (y1 + y2) / 2;

                            const strokeColor = isDown
                                ? '#f43f5e'
                                : isConnected
                                ? '#38bdf8'
                                : '#334155';

                            const strokeWidth = isConnected ? 3.5 : Math.max(1.5, Math.min(4, 1.5 + utilPct / 25));

                            const midX = (x1 + x2) / 2;
                            const midY = (y1 + y2) / 2;

                            return (
                                <g key={edge.id} className={`transition-opacity duration-300 ${isDimmed ? 'opacity-20' : 'opacity-100'}`}>
                                    <path
                                        d={`M ${x1} ${y1} C ${x1} ${cy}, ${x2} ${cy}, ${x2} ${y2}`}
                                        fill="none"
                                        stroke={strokeColor}
                                        strokeWidth={strokeWidth}
                                        strokeDasharray={isConnected ? '6 4' : 'none'}
                                        className={isConnected ? 'animate-pulse' : ''}
                                    />

                                    <g transform={`translate(${midX}, ${midY})`}>
                                        <rect
                                            x="-24"
                                            y="-9"
                                            width="48"
                                            height="18"
                                            rx="9"
                                            fill="#0f172a"
                                            stroke={isConnected ? '#38bdf8' : '#334155'}
                                            strokeWidth="1"
                                        />
                                        <text
                                            x="0"
                                            y="3.5"
                                            textAnchor="middle"
                                            className="text-[9px] font-semibold fill-slate-300"
                                        >
                                            {utilPct}%
                                        </text>
                                    </g>
                                </g>
                            );
                        })}
                    </g>

                    <g className="nodes">
                        {nodes.map((node) => {
                            const pos = nodePositions.get(node.id);

                            if (!pos) {
                                return null;
                            }

                            const isSelected = selectedId === node.id;
                            const isParent = parentIds.has(node.id);
                            const isChild = childIds.has(node.id);
                            const isRelated = isSelected || isParent || isChild;
                            const isDimmed = selectedId !== null && !isRelated;

                            const nodeHealth = (health?.devices ?? []).find((d) => Number(d.device_id) === node.id);
                            const style = statusOf(nodeHealth?.status);
                            const nodeLatest = latest?.devices?.[node.id] ?? {};

                            let borderClass = 'border-slate-800 bg-slate-900/90';
                            let badgeText = null;
                            let badgeClass = '';

                            if (isSelected) {
                                borderClass = 'border-sky-400 bg-sky-950/60 ring-2 ring-sky-400/50 shadow-lg shadow-sky-500/20';
                                badgeText = 'Foco';
                                badgeClass = 'bg-sky-500 text-white';
                            } else if (isParent) {
                                borderClass = 'border-amber-400/80 bg-amber-950/40 ring-1 ring-amber-400/50';
                                badgeText = 'Pai (Uplink)';
                                badgeClass = 'bg-amber-500 text-slate-950';
                            } else if (isChild) {
                                borderClass = 'border-emerald-400/80 bg-emerald-950/40 ring-1 ring-emerald-400/50';
                                badgeText = 'Filho (Downlink)';
                                badgeClass = 'bg-emerald-500 text-slate-950';
                            }

                            return (
                                <foreignObject
                                    key={node.id}
                                    x={pos.x - 75}
                                    y={pos.y - 30}
                                    width={150}
                                    height={66}
                                    className={`transition-all duration-300 ${isDimmed ? 'opacity-25 hover:opacity-75' : 'opacity-100'}`}
                                >
                                    <div
                                        onClick={() => onSelectNode(isSelected ? null : node.id)}
                                        className={`group relative flex h-full w-full cursor-pointer flex-col justify-between rounded-xl border p-2 text-left transition hover:scale-105 hover:border-slate-600 ${borderClass}`}
                                    >
                                        <div className="flex items-center justify-between gap-1">
                                            <div className="flex items-center gap-1.5 overflow-hidden">
                                                <div className={`text-slate-400 group-hover:text-slate-200 ${isSelected ? 'text-sky-300' : ''}`}>
                                                    <DeviceIcon kind={node.kind} className="h-4 w-4" />
                                                </div>
                                                <span className="truncate text-xs font-bold text-slate-100">
                                                    {node.name}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-1">
                                                <span className={`h-2 w-2 rounded-full ${style.dot}`} />
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between text-[10px] text-slate-400">
                                            <span className="truncate font-mono">{node.ip}</span>
                                            {nodeLatest.ping_rtt_ms !== undefined && (
                                                <span className="font-semibold text-slate-300">
                                                    {nodeLatest.ping_rtt_ms}ms
                                                </span>
                                            )}
                                        </div>

                                        {badgeText && (
                                            <div className="absolute -top-2.5 right-2">
                                                <span className={`rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase shadow ${badgeClass}`}>
                                                    {badgeText}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </foreignObject>
                            );
                        })}
                    </g>
                </svg>
            </div>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800/80 pt-3 text-xs text-slate-400">
                <div className="flex items-center gap-4">
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-emerald-400" /> Saudável
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-amber-400" /> Degradado
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="h-2 w-2 rounded-full bg-rose-500" /> Crítico
                    </span>
                </div>

                <div className="text-[11px] text-slate-500">
                    Clique num nó para destacar seus enlaces de subida (pais) e descida (filhos).
                </div>
            </div>
        </div>
    );
}
