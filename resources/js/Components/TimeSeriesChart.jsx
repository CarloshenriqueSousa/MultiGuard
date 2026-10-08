/**
 * Grafico de serie temporal em uPlot com faixas de incidente desenhadas por tras da linha (o conceito de
 * annotations do Grafana). O grafico e criado uma vez por combinacao de rotulo, cor e altura; os dados e os
 * incidentes entram por efeitos separados para que atualizar o polling nao recrie o canvas. As faixas sao
 * pintadas no gancho drawClear, que o uPlot chama antes de desenhar a serie, lendo os incidentes de uma ref
 * para o gancho nunca enxergar uma lista antiga. A largura acompanha o container por ResizeObserver.
 */

import { useEffect, useRef } from 'react';
import uPlot from 'uplot';
import 'uplot/dist/uPlot.min.css';

const BAND = {
    critical: 'rgba(244, 63, 94, 0.20)',
    warning: 'rgba(245, 158, 11, 0.20)',
};

export default function TimeSeriesChart({ timestamps, values, label, color, incidents, height = 260 }) {
    const container = useRef(null);
    const plot = useRef(null);
    const incidentsRef = useRef(incidents);

    incidentsRef.current = incidents;

    useEffect(() => {
        const element = container.current;

        const options = {
            width: element.clientWidth,
            height,
            scales: { x: { time: true } },
            axes: [
                { stroke: '#64748b', grid: { stroke: '#1e293b', width: 1 }, ticks: { stroke: '#1e293b' } },
                { stroke: '#64748b', grid: { stroke: '#1e293b', width: 1 }, ticks: { stroke: '#1e293b' } },
            ],
            series: [{}, { label, stroke: color, width: 2, fill: `${color}22` }],
            hooks: {
                drawClear: [
                    (u) => {
                        const { left, top, width, height: boxHeight } = u.bbox;

                        u.ctx.save();

                        incidentsRef.current.forEach((incident) => {
                            const start = Math.max(left, u.valToPos(incident.started_ts, 'x', true));
                            const end = Math.min(left + width, u.valToPos(Math.max(incident.ended_ts, incident.started_ts + 5), 'x', true));

                            if (!(end > start)) {
                                return;
                            }

                            u.ctx.fillStyle = BAND[incident.severity] ?? BAND.warning;
                            u.ctx.fillRect(start, top, end - start, boxHeight);
                        });

                        u.ctx.restore();
                    },
                ],
            },
        };

        plot.current = new uPlot(options, [[], []], element);

        const observer = new ResizeObserver(() => {
            plot.current?.setSize({ width: element.clientWidth, height });
        });

        observer.observe(element);

        return () => {
            observer.disconnect();
            plot.current?.destroy();
            plot.current = null;
        };
    }, [label, color, height]);

    useEffect(() => {
        plot.current?.setData([timestamps, values]);
    }, [timestamps, values, label, color, height]);

    useEffect(() => {
        plot.current?.redraw();
    }, [incidents]);

    return <div ref={container} className="w-full" />;
}
