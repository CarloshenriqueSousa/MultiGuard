/**
 * Pagina do mapa 3D de uma unidade. Consulta topologia (uma vez), saude, ultimos valores e incidentes, e entrega
 * ao mapa. Clicar em um incidente poe o raio de impacto em foco no mapa e seleciona o dispositivo raiz; clicar em
 * um no abre os detalhes dele (estado, ultimos valores, enlace de subida e incidentes que o envolvem) e o
 * grafico dele com as faixas de incidente. O alvo do grafico e unico: escolher no mapa ou no seletor do painel
 * leva ao mesmo estado. O componente se chama MapPage para nao esconder o Map nativo do JavaScript.
 */

import { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from '../Components/Header';
import NavTabs from '../Components/NavTabs';
import NetworkMap3D from '../Components/NetworkMap3D';
import SeriesPanel from '../Components/SeriesPanel';
import { CAUSE, formatDuration, formatTime, statusOf } from '../lib/format';
import { usePolling } from '../lib/usePolling';

const LEGEND = [
    { label: 'Saudável', dot: 'bg-emerald-400' },
    { label: 'Degradado', dot: 'bg-amber-400' },
    { label: 'Crítico ou fora do ar', dot: 'bg-rose-500' },
    { label: 'Sem dados', dot: 'bg-slate-500' },
];

const fmt = (value, unit) => (value === undefined ? '--' : `${value}${unit}`);

function IncidentFocusList({ incidents, focusId, onFocus }) {
    const now = Math.floor(Date.now() / 1000);

    return (
        <section className="rounded-lg border border-slate-800 bg-slate-900 p-4">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-300">Raio de impacto</h2>
                {focusId !== null && (
                    <button type="button" onClick={() => onFocus(null)} className="text-xs text-sky-400 hover:text-sky-300">
                        Limpar foco
                    </button>
                )}
            </div>
            <p className="mt-1 text-xs text-slate-500">Clique num incidente para realçar no mapa o que ele afeta.</p>

            {incidents.length === 0 && <p className="mt-4 text-sm text-slate-500">Nenhum incidente na última hora.</p>}

            <ul className="mt-3 space-y-2">
                {incidents.slice(0, 8).map((incident) => {
                    const open = incident.status === 'open';
                    const duration = (open ? now : incident.ended_ts) - incident.started_ts;
                    const active = focusId === incident.id;
                    const count = (incident.affected_device_ids ?? []).length;

                    return (
                        <li key={incident.id}>
                            <button
                                type="button"
                                onClick={() => onFocus(active ? null : incident)}
                                className={`w-full rounded border p-2.5 text-left transition hover:bg-slate-800/60 ${
                                    active ? 'border-sky-500 bg-slate-800/60' : 'border-slate-800 bg-slate-950/50'
                                }`}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <span className="text-sm font-semibold text-slate-100">{CAUSE[incident.cause] ?? incident.cause}</span>
                                    <span className={`text-[10px] font-bold uppercase ${open ? 'text-rose-400' : 'text-slate-500'}`}>
                                        {open ? 'aberto' : 'resolvido'}
                                    </span>
                                </div>
                                <div className="mt-1 text-xs text-slate-400">
                                    {incident.root_device ?? 'unidade'} · {count} afetado(s) · {formatDuration(duration)}
                                </div>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function NodeDetails({ node, edges, health, latest, incidents }) {
    const row = (health?.devices ?? []).find((item) => Number(item.device_id) === node.id);
    const style = statusOf(row?.status);
    const values = latest?.devices?.[node.id] ?? {};

    const uplink = edges.find((edge) => edge.to_device_id === node.id) ?? null;
    const uplinkRow = uplink ? (health?.links ?? []).find((item) => Number(item.link_id) === uplink.id) : null;
    const uplinkStyle = statusOf(uplinkRow?.status);
    const uplinkValues = uplink ? (latest?.links?.[uplink.id] ?? {}) : {};

    const related = incidents
        .filter((incident) => incident.root_device_id === node.id || (incident.affected_device_ids ?? []).includes(node.id))
        .slice(0, 5);

    return (
        <section className="rounded-lg border border-slate-800 bg-slate-900 p-4">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-lg font-bold text-slate-100">{node.name}</h2>
                    <p className="text-xs text-slate-500">
                        {node.layer} · {node.ip}
                    </p>
                </div>
                <div className="text-right">
                    <div className={`text-sm font-bold ${style.text}`}>{style.label}</div>
                    <div className={`text-2xl font-bold ${style.text}`}>{row?.score ?? '--'}</div>
                </div>
            </div>

            <dl className="mt-4 grid grid-cols-3 gap-2 text-center">
                <div className="rounded bg-slate-950/50 p-2">
                    <dt className="text-[10px] uppercase text-slate-500">Perda</dt>
                    <dd className="text-sm font-semibold text-slate-200">{fmt(values.ping_loss_pct, '%')}</dd>
                </div>
                <div className="rounded bg-slate-950/50 p-2">
                    <dt className="text-[10px] uppercase text-slate-500">RTT</dt>
                    <dd className="text-sm font-semibold text-slate-200">{fmt(values.ping_rtt_ms, ' ms')}</dd>
                </div>
                <div className="rounded bg-slate-950/50 p-2">
                    <dt className="text-[10px] uppercase text-slate-500">Jitter</dt>
                    <dd className="text-sm font-semibold text-slate-200">{fmt(values.ping_jitter_ms, ' ms')}</dd>
                </div>
            </dl>

            {uplink && (
                <div className="mt-3 rounded bg-slate-950/50 p-2 text-xs text-slate-400">
                    Enlace de subida: <span className={`font-semibold ${uplinkStyle.text}`}>{uplinkStyle.label}</span>
                    {' · '}uso {fmt(uplinkValues.if_util_pct, '%')}
                    {' · '}erros {fmt(uplinkValues.if_errors_per_min, '/min')}
                </div>
            )}

            <div className="mt-4 text-[11px] uppercase tracking-wider text-slate-500">Eventos</div>
            {related.length === 0 && <p className="mt-1 text-xs text-slate-500">Nenhum incidente envolvendo este dispositivo.</p>}
            <ul className="mt-1 space-y-1">
                {related.map((incident) => (
                    <li key={incident.id} className="text-xs text-slate-400">
                        {formatTime(incident.started_ts)} · {CAUSE[incident.cause] ?? incident.cause}
                        {incident.root_device_id === node.id ? ' (raiz)' : ' (afetado)'}
                    </li>
                ))}
            </ul>
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
            <Head title="Mapa 3D · Multi-Guard" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <NavTabs current="map" />

                <Header
                    unitName={topology.data?.unit?.name ?? health.data?.unit?.name ?? unitSlug}
                    health={health.data?.health ?? null}
                    evaluatedAt={health.data?.evaluated_at ?? null}
                />

                {errors.length > 0 && (
                    <p className="rounded border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-300">
                        Erro ao consultar a API: {errors.join(' · ')}
                    </p>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-3 lg:col-span-2">
                        <NetworkMap3D
                            topology={topology.data}
                            health={health.data}
                            latest={latest.data}
                            focus={focus}
                            selectedId={selectedId}
                            onSelectNode={handleSelectNode}
                        />

                        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-slate-400">
                            {LEGEND.map((item) => (
                                <span key={item.label} className="flex items-center gap-1.5">
                                    <span className={`h-2.5 w-2.5 rounded-full ${item.dot}`} />
                                    {item.label}
                                </span>
                            ))}
                            <span>Espessura do enlace = utilização · tracejado = caído ou sem medição</span>
                            <span>Arraste para girar · roda para zoom · clique num nó</span>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <IncidentFocusList incidents={incidentList} focusId={focus ? focusId : null} onFocus={handleFocus} />

                        {selectedNode && (
                            <NodeDetails
                                node={selectedNode}
                                edges={edges}
                                health={health.data}
                                latest={latest.data}
                                incidents={incidentList}
                            />
                        )}
                    </div>
                </div>

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
        </>
    );
}
