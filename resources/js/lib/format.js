/**
 * Vocabulario visual do console: estilos por estado de saude, nomes das causas do motor de diagnostico,
 * ordem e rotulos das camadas da topologia e formatadores de hora e duracao. As classes do Tailwind aparecem
 * escritas por extenso porque o Tailwind so gera as classes que encontra literalmente no codigo.
 */

export const STATUS = {
    healthy: { label: 'Saudável', text: 'text-emerald-400', dot: 'bg-emerald-400', border: 'border-emerald-500/30' },
    degraded: { label: 'Degradado', text: 'text-amber-400', dot: 'bg-amber-400', border: 'border-amber-500/30' },
    critical: { label: 'Crítico', text: 'text-rose-500', dot: 'bg-rose-500', border: 'border-rose-500/40' },
    down: { label: 'Fora do ar', text: 'text-rose-500', dot: 'bg-rose-500', border: 'border-rose-500/40' },
    no_data: { label: 'Sem dados', text: 'text-slate-400', dot: 'bg-slate-500', border: 'border-slate-700' },
};

export const statusOf = (status) => STATUS[status] ?? STATUS.no_data;

export const CAUSE = {
    uplink: 'Uplink',
    cable_port: 'Cabo ou porta',
    congestion: 'Congestionamento',
    local_device: 'Falha local',
    no_data: 'Sem dados',
};

export const LAYERS = [
    { id: 'gateway', label: 'Gateway' },
    { id: 'core', label: 'Núcleo' },
    { id: 'server', label: 'Servidor' },
    { id: 'distribution', label: 'Distribuição' },
    { id: 'access', label: 'Acesso' },
];

export function formatTime(seconds) {
    return new Date(seconds * 1000).toLocaleTimeString('pt-BR');
}

export function formatDuration(seconds) {
    const total = Math.max(0, Math.round(seconds));

    if (total < 60) {
        return `${total}s`;
    }

    const minutes = Math.floor(total / 60);

    if (minutes < 60) {
        return `${minutes}min ${total % 60}s`;
    }

    return `${Math.floor(minutes / 60)}h ${minutes % 60}min`;
}
