/**
 * Mostra o ultimo estado de cada dispositivo (agrupados por camada, na ordem gateway ate acesso) e de cada
 * enlace, com a cor do estado e o indice de saude. Cada cartao e um botao: clicar seleciona aquele alvo para o
 * painel de graficos. O alvo selecionado vem em selected no formato "device:ID" ou "link:ID".
 */

import { LAYERS, statusOf } from '../lib/format';

function Card({ title, subtitle, row, active, onClick }) {
    const style = statusOf(row?.status);

    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded border bg-slate-950/50 p-2.5 text-left transition hover:bg-slate-800/60 ${style.border} ${active ? 'ring-1 ring-sky-500' : ''}`}
        >
            <div className="flex items-center justify-between gap-2">
                <span className="text-xs font-semibold text-slate-200">{title}</span>
                <span className={`h-2 w-2 rounded-full ${style.dot}`} />
            </div>
            <div className="mt-1 flex items-baseline justify-between">
                <span className="text-[11px] text-slate-500">{subtitle}</span>
                <span className={`text-sm font-bold ${style.text}`}>{row?.score ?? '--'}</span>
            </div>
        </button>
    );
}

export default function StatusGrid({ nodes, edges, health, selected, onSelect }) {
    const deviceRows = new Map((health?.devices ?? []).map((row) => [Number(row.device_id), row]));
    const linkRows = new Map((health?.links ?? []).map((row) => [Number(row.link_id), row]));
    const names = new Map(nodes.map((node) => [node.id, node.name]));

    return (
        <section className="rounded-lg border border-slate-800 bg-slate-900 p-4">
            <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-300">Estado da rede</h2>

            <div className="mt-4 space-y-4">
                {LAYERS.map((layer) => {
                    const members = nodes.filter((node) => node.layer === layer.id);

                    if (members.length === 0) {
                        return null;
                    }

                    return (
                        <div key={layer.id}>
                            <div className="mb-2 text-[11px] uppercase tracking-wider text-slate-500">{layer.label}</div>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                                {members.map((node) => (
                                    <Card
                                        key={node.id}
                                        title={node.name}
                                        subtitle={node.ip}
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
                    <div className="mb-2 text-[11px] uppercase tracking-wider text-slate-500">Enlaces</div>
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
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
