/**
 * Painel de grafico com seletores de alvo (dispositivo ou enlace), metrica e periodo. As metricas oferecidas
 * dependem do tipo de alvo: ping para dispositivos, SNMP para enlaces. As faixas de incidente mostradas sao so
 * as relevantes para o alvo: para um dispositivo, incidentes em que ele e a raiz ou esta no raio de impacto; para
 * um enlace, os que apontam aquele enlace ou o dispositivo da ponta de baixo. Consulta a serie a cada 5 segundos.
 */

import { usePolling } from '../lib/usePolling';
import TimeSeriesChart from './TimeSeriesChart';

const DEVICE_METRICS = [
    { id: 'ping_rtt_ms', label: 'RTT (ms)', color: '#38bdf8' },
    { id: 'ping_loss_pct', label: 'Perda (%)', color: '#f43f5e' },
    { id: 'ping_jitter_ms', label: 'Jitter (ms)', color: '#818cf8' },
];

const LINK_METRICS = [
    { id: 'if_util_pct', label: 'Utilização (%)', color: '#f59e0b' },
    { id: 'if_errors_per_min', label: 'Erros por minuto', color: '#f43f5e' },
    { id: 'if_oper_status', label: 'Estado da porta (1 = ativa)', color: '#34d399' },
];

const PERIODS = [
    { minutes: 5, label: '5 min' },
    { minutes: 15, label: '15 min' },
    { minutes: 60, label: '1 hora' },
    { minutes: 360, label: '6 horas' },
    { minutes: 1440, label: '24 horas' },
];

const SELECT = 'rounded border border-slate-700 bg-slate-950 px-2 py-1 text-sm text-slate-200';

export default function SeriesPanel({ unitSlug, nodes, edges, incidents, target, onTargetChange, metricId, onMetricChange, minutes, onMinutesChange }) {
    const names = new Map(nodes.map((node) => [node.id, node.name]));
    const effective = target ?? (nodes[0] ? `device:${nodes[0].id}` : null);
    const [kind, rawId] = effective ? effective.split(':') : [null, null];
    const id = Number(rawId);
    const metrics = kind === 'link' ? LINK_METRICS : DEVICE_METRICS;
    const metric = metrics.find((item) => item.id === metricId) ?? metrics[0];

    const url = effective
        ? `/api/units/${unitSlug}/series?${kind}_id=${id}&metric=${metric.id}&minutes=${minutes}`
        : null;

    const series = usePolling(url, 5000);
    const timestamps = series.data?.data?.timestamps ?? [];
    const values = series.data?.data?.values ?? [];

    const edge = kind === 'link' ? edges.find((item) => item.id === id) : null;

    const relevant = incidents.filter((incident) => {
        if (kind === 'device') {
            return incident.root_device_id === id || (incident.affected_device_ids ?? []).includes(id);
        }

        return incident.link_id === id || (edge && incident.root_device_id === edge.to_device_id);
    });

    return (
        <section className="rounded-lg border border-slate-800 bg-slate-900 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-300">Séries temporais</h2>

                <div className="flex flex-wrap gap-2">
                    <select className={SELECT} value={effective ?? ''} onChange={(event) => onTargetChange(event.target.value)}>
                        <optgroup label="Dispositivos">
                            {nodes.map((node) => (
                                <option key={node.id} value={`device:${node.id}`}>{node.name}</option>
                            ))}
                        </optgroup>
                        <optgroup label="Enlaces">
                            {edges.map((item) => (
                                <option key={item.id} value={`link:${item.id}`}>
                                    {names.get(item.from_device_id)} → {names.get(item.to_device_id)}
                                </option>
                            ))}
                        </optgroup>
                    </select>

                    <select className={SELECT} value={metric.id} onChange={(event) => onMetricChange(event.target.value)}>
                        {metrics.map((item) => (
                            <option key={item.id} value={item.id}>{item.label}</option>
                        ))}
                    </select>

                    <select className={SELECT} value={minutes} onChange={(event) => onMinutesChange(Number(event.target.value))}>
                        {PERIODS.map((item) => (
                            <option key={item.minutes} value={item.minutes}>{item.label}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="mt-4">
                <TimeSeriesChart
                    timestamps={timestamps}
                    values={values}
                    label={metric.label}
                    color={metric.color}
                    incidents={relevant}
                />
            </div>

            {series.error && <p className="mt-2 text-xs text-rose-400">Erro ao consultar a série: {series.error}</p>}
            {!series.error && series.data && timestamps.length === 0 && (
                <p className="mt-2 text-xs text-slate-500">Sem pontos neste período. O simulador está rodando?</p>
            )}
            {kind === 'device' && metric.id !== 'ping_loss_pct' && (
                <p className="mt-2 text-xs text-slate-600">
                    Dispositivo fora do ar não envia RTT nem jitter: a linha some, e a faixa vermelha marca o incidente.
                </p>
            )}
        </section>
    );
}
