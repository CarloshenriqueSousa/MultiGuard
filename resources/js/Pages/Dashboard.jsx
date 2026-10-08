/**
 * Pagina do console de uma unidade. Reune os dados de topologia (uma vez), saude (a cada 3 s) e incidentes
 * (a cada 5 s) e distribui para os componentes. A selecao de alvo, metrica e periodo mora aqui para que clicar
 * num cartao de dispositivo ou enlace atualize o painel de graficos. Erros de qualquer consulta aparecem num
 * aviso no topo, sem derrubar o restante da pagina.
 */

import { useState } from 'react';
import { Head } from '@inertiajs/react';
import Header from '../Components/Header';
import IncidentList from '../Components/IncidentList';
import SeriesPanel from '../Components/SeriesPanel';
import StatusGrid from '../Components/StatusGrid';
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

    return (
        <>
            <Head title="Multi-Guard" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
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
