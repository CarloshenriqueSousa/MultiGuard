/**
 * Painel principal do console com cards executivos de NOC, grade de dispositivos, series temporais e incidentes.
 */

import { useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from '../Components/Header';
import IncidentList from '../Components/IncidentList';
import SeriesPanel from '../Components/SeriesPanel';
import StatusGrid from '../Components/StatusGrid';
import NavTabs from '../Components/NavTabs';
import { usePolling } from '../lib/usePolling';

export default function Dashboard({ unitSlug }) {
    const base = `/api/units/${unitSlug}`;
    const topology = usePolling(`${base}/topology`, null);
    const health = usePolling(`${base}/health`, 3000);
    const incidents = usePolling(`${base}/incidents?minutes=60`, 5000);

    const [target, setTarget] = useState(null);
    const [metricId, setMetricId] = useState(null);
    const [minutes, setMinutes] = useState(15);

    const nodes = topology.data?.nodes ?? [];
    const edges = topology.data?.edges ?? [];
    const incidentList = incidents.data?.incidents ?? [];
    const errors = [topology.error, health.error, incidents.error].filter(Boolean);

    const healthData = health.data?.health;
    const deviceStatuses = health.data?.devices ?? [];
    const healthyDevices = deviceStatuses.filter((d) => d.status === 'healthy').length;
    const openIncidents = incidentList.filter((i) => i.status === 'open').length;

    return (
        <>
            <Head title="Console de Operações · Multi-Guard" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <NavTabs current="dashboard" />

                <Header
                    unitName={topology.data?.unit?.name ?? health.data?.unit?.name ?? unitSlug}
                    health={healthData ?? null}
                    evaluatedAt={health.data?.evaluated_at ?? null}
                />

                {errors.length > 0 && (
                    <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3.5 text-sm text-rose-300">
                        Erro ao consultar a API: {errors.join(' · ')}
                    </div>
                )}

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div className="rounded-xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg backdrop-blur">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Saúde Global</span>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-2xl font-black text-emerald-400">{healthData?.score ?? 100}%</span>
                            <span className="text-xs text-slate-400 capitalize">{healthData?.status ?? 'Estável'}</span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg backdrop-blur">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Dispositivos</span>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-2xl font-black text-slate-100">{nodes.length}</span>
                            <span className="text-xs text-emerald-400 font-semibold">{healthyDevices || nodes.length} online</span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg backdrop-blur">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Enlaces Ativos</span>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className="text-2xl font-black text-slate-100">{edges.length}</span>
                            <span className="text-xs text-sky-400 font-semibold">Monitorados</span>
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg backdrop-blur">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Incidentes Abertos</span>
                        <div className="mt-1 flex items-baseline gap-2">
                            <span className={`text-2xl font-black ${openIncidents > 0 ? 'text-rose-400' : 'text-slate-100'}`}>
                                {openIncidents}
                            </span>
                            <span className={`text-xs font-semibold ${openIncidents > 0 ? 'text-rose-400' : 'text-slate-500'}`}>
                                {openIncidents > 0 ? 'Requer atenção' : 'Normalidade'}
                            </span>
                        </div>
                    </div>
                </div>

                <StatusGrid
                    nodes={nodes}
                    edges={edges}
                    health={health.data}
                    selected={target}
                    onSelect={setTarget}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
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

                    <IncidentList incidents={incidentList} nodes={nodes} />
                </div>
            </div>
        </>
    );
}
