/**
 * Pagina de topologia da rede em 2D com inspecao profunda de dispositivos, diagnostico e relacoes de parentesco.
 */

import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from '../Components/Header';
import NavTabs from '../Components/NavTabs';
import NetworkMap2D from '../Components/NetworkMap2D';
import SeriesPanel from '../Components/SeriesPanel';
import { CAUSE, formatDuration, formatTime, statusOf } from '../lib/format';
import { usePolling } from '../lib/usePolling';

const fmt = (value, unit) => (value === undefined || value === null ? '--' : `${value}${unit}`);

function IncidentFocusList({ incidents, focusId, onFocus }) {
    const now = Math.floor(Date.now() / 1000);

    return (
        <section className="rounded-xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg backdrop-blur">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-300">
                    Incidentes e Raio de Impacto
                </h2>
                {focusId !== null && (
                    <button
                        type="button"
                        onClick={() => onFocus(null)}
                        className="text-xs font-medium text-sky-400 hover:text-sky-300"
                    >
                        Limpar foco
                    </button>
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500">
                Selecione um incidente para destacar no mapa a cadeia de nós afetados.
            </p>

            {incidents.length === 0 && (
                <div className="mt-3 rounded-lg border border-slate-800/80 bg-slate-950/50 p-3 text-center text-xs text-slate-500">
                    Nenhum incidente registrado na última hora.
                </div>
            )}

            <ul className="mt-3 space-y-2">
                {incidents.slice(0, 6).map((incident) => {
                    const open = incident.status === 'open';
                    const duration = (open ? now : incident.ended_ts) - incident.started_ts;
                    const active = focusId === incident.id;
                    const count = (incident.affected_device_ids ?? []).length;

                    return (
                        <li key={incident.id}>
                            <button
                                type="button"
                                onClick={() => onFocus(active ? null : incident)}
                                className={`w-full rounded-lg border p-2.5 text-left transition hover:bg-slate-800/80 ${
                                    active
                                        ? 'border-sky-500 bg-slate-800/80 shadow-md shadow-sky-500/10'
                                        : 'border-slate-800 bg-slate-950/60'
                                }`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm font-semibold text-slate-100">
                                        {CAUSE[incident.cause] ?? incident.cause}
                                    </span>
                                    <span
                                        className={`rounded px-1.5 py-0.5 text-[10px] font-bold uppercase ${
                                            open ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-slate-800 text-slate-400'
                                        }`}
                                    >
                                        {open ? 'aberto' : 'resolvido'}
                                    </span>
                                </div>
                                <div className="mt-1.5 flex items-center justify-between text-xs text-slate-400">
                                    <span>Raiz: {incident.root_device ?? 'unidade'}</span>
                                    <span>{count} afetado(s) · {formatDuration(duration)}</span>
                                </div>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function NodeDetails({ node, nodes, edges, health, latest, incidents, onSelectNode }) {
    const row = (health?.devices ?? []).find((item) => Number(item.device_id) === node.id);
    const style = statusOf(row?.status);
    const values = latest?.devices?.[node.id] ?? {};

    const uplinkEdges = edges.filter((edge) => edge.to_device_id === node.id);
    const downlinkEdges = edges.filter((edge) => edge.from_device_id === node.id);

    const parents = uplinkEdges.map((edge) => {
        const parentNode = nodes.find((n) => n.id === edge.from_device_id);
        const linkHealth = (health?.links ?? []).find((l) => Number(l.link_id) === edge.id);
        const linkValues = latest?.links?.[edge.id] ?? {};
        return { edge, node: parentNode, health: linkHealth, values: linkValues };
    }).filter((p) => p.node);

    const children = downlinkEdges.map((edge) => {
        const childNode = nodes.find((n) => n.id === edge.to_device_id);
        const linkHealth = (health?.links ?? []).find((l) => Number(l.link_id) === edge.id);
        const linkValues = latest?.links?.[edge.id] ?? {};
        return { edge, node: childNode, health: linkHealth, values: linkValues };
    }).filter((c) => c.node);

    const siblingNodes = useMemo(() => {
        if (parents.length === 0) return [];
        const parentId = parents[0].node.id;
        return edges
            .filter((e) => e.from_device_id === parentId && e.to_device_id !== node.id)
            .map((e) => nodes.find((n) => n.id === e.to_device_id))
            .filter(Boolean);
    }, [parents, edges, node, nodes]);

    const relatedIncidents = incidents
        .filter((incident) => incident.root_device_id === node.id || (incident.affected_device_ids ?? []).includes(node.id))
        .slice(0, 4);

    return (
        <section className="rounded-xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl backdrop-blur space-y-5">
            <div className="flex items-start justify-between gap-3 border-b border-slate-800 pb-3">
                <div>
                    <div className="flex items-center gap-2">
                        <h2 className="text-xl font-bold text-slate-100">{node.name}</h2>
                        <span className="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-400">
                            {node.layer}
                        </span>
                    </div>
                    <p className="mt-0.5 font-mono text-xs text-slate-400">{node.ip}</p>
                </div>

                <div className="text-right">
                    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold ${style.text} bg-slate-950 border ${style.border}`}>
                        <span className={`h-2 w-2 rounded-full ${style.dot}`} />
                        {style.label}
                    </span>
                    <div className="mt-1 text-2xl font-black text-slate-100">{row?.score ?? '--'}</div>
                </div>
            </div>

            <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    O que está acontecendo
                </h3>
                <div className="mt-2 rounded-lg border border-slate-800/80 bg-slate-950/60 p-3 text-xs leading-relaxed text-slate-300">
                    {row?.status === 'healthy' && (
                        <p className="text-emerald-300">
                            ✓ Operação estável. Sem perdas de pacotes, tempos de resposta dentro dos limites normais e enlaces com capacidade adequada.
                        </p>
                    )}
                    {row?.status === 'degraded' && (
                        <p className="text-amber-300">
                            ⚠ Atenção: Degradação de sinal ou oscilação de latência/jitter detectada. Verifique os enlaces conectados abaixo.
                        </p>
                    )}
                    {(row?.status === 'critical' || row?.status === 'down') && (
                        <p className="text-rose-300">
                            ✕ Falha crítica ou perda severa de pacotes. Este dispositivo requer intervenção imediata.
                        </p>
                    )}

                    {relatedIncidents.length > 0 && (
                        <ul className="mt-2 space-y-1 border-t border-slate-800/80 pt-2 text-[11px] text-slate-400">
                            {relatedIncidents.map((inc) => (
                                <li key={inc.id}>
                                    • {formatTime(inc.started_ts)}: {CAUSE[inc.cause] ?? inc.cause}
                                    {inc.root_device_id === node.id ? ' (origem da falha)' : ' (afetado)'}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Como está (Telemetria ao vivo)
                </h3>
                <dl className="mt-2 grid grid-cols-3 gap-2 text-center">
                    <div className="rounded-lg border border-slate-800/80 bg-slate-950/50 p-2.5">
                        <dt className="text-[10px] font-bold uppercase text-slate-500">Perda</dt>
                        <dd className={`mt-0.5 text-base font-bold ${values.ping_loss_pct > 0 ? 'text-rose-400' : 'text-slate-100'}`}>
                            {fmt(values.ping_loss_pct, '%')}
                        </dd>
                    </div>
                    <div className="rounded-lg border border-slate-800/80 bg-slate-950/50 p-2.5">
                        <dt className="text-[10px] font-bold uppercase text-slate-500">RTT</dt>
                        <dd className="mt-0.5 text-base font-bold text-sky-400">
                            {fmt(values.ping_rtt_ms, ' ms')}
                        </dd>
                    </div>
                    <div className="rounded-lg border border-slate-800/80 bg-slate-950/50 p-2.5">
                        <dt className="text-[10px] font-bold uppercase text-slate-500">Jitter</dt>
                        <dd className="mt-0.5 text-base font-bold text-indigo-400">
                            {fmt(values.ping_jitter_ms, ' ms')}
                        </dd>
                    </div>
                </dl>
            </div>

            <div className="space-y-3">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Seus Parentes (Conexões Diretas)
                </h3>

                {parents.length > 0 && (
                    <div className="rounded-lg border border-amber-500/20 bg-amber-500/5 p-3">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-bold text-amber-400">▲ Conectado Acima (Pai / Uplink)</span>
                        </div>
                        {parents.map(({ edge, node: pNode, values: pVal }) => (
                            <div key={pNode.id} className="mt-2 flex items-center justify-between gap-2">
                                <div>
                                    <button
                                        type="button"
                                        onClick={() => onSelectNode(pNode.id)}
                                        className="font-bold text-slate-100 hover:text-sky-400 text-left transition"
                                    >
                                        {pNode.name} <span className="font-normal font-mono text-xs text-slate-400">({pNode.ip})</span>
                                    </button>
                                    <div className="text-[11px] text-slate-400">
                                        Enlace: uso {fmt(pVal.if_util_pct, '%')} · erros: {fmt(pVal.if_errors_per_min, '/min')}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onSelectNode(pNode.id)}
                                    className="rounded border border-slate-700 bg-slate-800 px-2 py-1 text-[10px] text-slate-300 hover:bg-slate-700"
                                >
                                    Focar
                                </button>
                            </div>
                        ))}
                    </div>
                )}

                {children.length > 0 && (
                    <div className="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-bold text-emerald-400">▼ Conectados Abaixo (Filhos / Downlinks)</span>
                            <span className="text-[11px] text-slate-400">{children.length} dispositivo(s)</span>
                        </div>
                        <ul className="mt-2 space-y-2">
                            {children.map(({ edge, node: cNode, values: cVal }) => (
                                <li key={cNode.id} className="flex items-center justify-between gap-2 border-t border-slate-800/60 pt-2 first:border-0 first:pt-0">
                                    <div>
                                        <button
                                            type="button"
                                            onClick={() => onSelectNode(cNode.id)}
                                            className="font-bold text-slate-100 hover:text-sky-400 text-left text-xs transition"
                                        >
                                            {cNode.name} <span className="font-normal font-mono text-[11px] text-slate-400">({cNode.ip})</span>
                                        </button>
                                        <div className="text-[10px] text-slate-400">
                                            Enlace: uso {fmt(cVal.if_util_pct, '%')}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => onSelectNode(cNode.id)}
                                        className="rounded border border-slate-700 bg-slate-800 px-2 py-0.5 text-[10px] text-slate-300 hover:bg-slate-700"
                                    >
                                        Focar
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                {siblingNodes.length > 0 && (
                    <div className="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                        <span className="text-[11px] font-semibold uppercase text-slate-400">
                            Dispositivos no mesmo nível (Irmãos):
                        </span>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {siblingNodes.map((sNode) => (
                                <button
                                    key={sNode.id}
                                    type="button"
                                    onClick={() => onSelectNode(sNode.id)}
                                    className="rounded border border-slate-800 bg-slate-900 px-2.5 py-1 text-xs text-slate-300 transition hover:border-slate-700 hover:text-white"
                                >
                                    {sNode.name}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}

export default function MapPage({ unitSlug }) {
    const base = `/api/units/${unitSlug}`;
    const topology = usePolling(`${base}/topology`, null);
    const health = usePolling(`${base}/health`, 3000);
    const latest = usePolling(`${base}/latest`, 3000);
    const incidents = usePolling(`${base}/incidents?minutes=60`, 5000);

    const [target, setTarget] = useState(null);
    const [metricId, setMetricId] = useState(null);
    const [minutes, setMinutes] = useState(15);
    const [focusId, setFocusId] = useState(null);

    const nodes = useMemo(() => topology.data?.nodes ?? [], [topology.data]);
    const edges = useMemo(() => topology.data?.edges ?? [], [topology.data]);
    const incidentList = useMemo(() => incidents.data?.incidents ?? [], [incidents.data]);

    const focus = useMemo(() => {
        const incident = incidentList.find((item) => item.id === focusId);

        if (!incident) {
            return null;
        }

        const ids = new Set(incident.affected_device_ids ?? []);

        if (incident.root_device_id) {
            ids.add(incident.root_device_id);
        }

        return { ids, rootId: incident.root_device_id };
    }, [incidentList, focusId]);

    const selectedId = target?.startsWith('device:') ? Number(target.split(':')[1]) : null;
    const selectedNode = nodes.find((node) => node.id === selectedId) ?? null;
    const errors = [topology.error, health.error, latest.error, incidents.error].filter(Boolean);

    const handleSelectNode = (id) => {
        setTarget(id === null ? null : `device:${id}`);
    };

    const handleFocus = (incident) => {
        if (!incident) {
            setFocusId(null);
            return;
        }

        setFocusId(incident.id);

        if (incident.root_device_id) {
            setTarget(`device:${incident.root_device_id}`);
        }
    };

    return (
        <>
            <Head title="Mapa de Topologia · Multi-Guard" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <NavTabs current="map" />

                <Header
                    unitName={topology.data?.unit?.name ?? health.data?.unit?.name ?? unitSlug}
                    health={health.data?.health ?? null}
                    evaluatedAt={health.data?.evaluated_at ?? null}
                />

                {errors.length > 0 && (
                    <p className="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-300">
                        Erro ao consultar a API: {errors.join(' · ')}
                    </p>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <NetworkMap2D
                            topology={topology.data}
                            health={health.data}
                            latest={latest.data}
                            focus={focus}
                            selectedId={selectedId}
                            onSelectNode={handleSelectNode}
                        />

                        <SeriesPanel
                            unitSlug={unitSlug}
                            nodes={nodes}
                            edges={edges}
                            incidents={incidentList}
                            target={target}
                            onTargetChange={setTarget}
                            metricId={metricId}
                            onMetricChange={setMetricId}
                            minutes={minutes}
                            onMinutesChange={setMinutes}
                        />
                    </div>

                    <div className="space-y-6">
                        {selectedNode ? (
                            <NodeDetails
                                node={selectedNode}
                                nodes={nodes}
                                edges={edges}
                                health={health.data}
                                latest={latest.data}
                                incidents={incidentList}
                                onSelectNode={handleSelectNode}
                            />
                        ) : (
                            <div className="rounded-xl border border-dashed border-slate-800 bg-slate-900/40 p-6 text-center text-slate-500">
                                <p className="text-sm font-medium">Nenhum dispositivo selecionado</p>
                                <p className="mt-1 text-xs">
                                    Clique em qualquer módulo no mapa para inspecionar seus parentes (subida/descida), status e telemetria.
                                </p>
                            </div>
                        )}

                        <IncidentFocusList
                            incidents={incidentList}
                            focusId={focus ? focusId : null}
                            onFocus={handleFocus}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
