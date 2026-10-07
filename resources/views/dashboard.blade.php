<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Guard | Network Monitoring Console</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/uplot@1.6.30/dist/uPlot.min.css">
    <script src="https://unpkg.com/uplot@1.6.30/dist/uPlot.iife.min.js"></script>

    <style>
        body { background-color: #0b0f19; color: #e2e8f0; }
        .uplot { font-family: inherit; }
        .u-legend { color: #94a3b8 !important; }
    </style>
</head>
<body class="min-h-screen p-6 font-sans antialiased">

    <header class="flex items-center justify-between pb-6 border-b border-slate-800">
        <div class="flex items-center space-x-3">
            <div class="w-4 h-4 rounded-full bg-emerald-500 animate-pulse"></div>
            <h1 class="text-xl font-bold tracking-wider text-slate-100">MULTI-GUARD</h1>
            <span class="px-2.5 py-0.5 text-xs font-semibold rounded bg-slate-800 text-slate-400 border border-slate-700">POC v1.0</span>
        </div>
        <div class="flex items-center space-x-4">
            <span class="text-sm text-slate-400">Unidade: <strong class="text-slate-200">Unidade Piloto Teste</strong></span>
            <div class="flex items-center px-3 py-1 rounded bg-slate-900 border border-slate-800 text-xs text-emerald-400 font-mono">
                ● Telemetria ao Vivo (Auto-refresh 3s)
            </div>
        </div>
    </header>

    <section class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="p-4 rounded-lg bg-slate-900/90 border border-slate-800">
            <span class="text-xs uppercase tracking-wider text-slate-400 font-medium">Latência Atual (RTT)</span>
            <div class="mt-2 flex items-baseline space-x-2">
                <span id="card-rtt" class="text-3xl font-bold text-sky-400">--</span>
                <span class="text-sm text-slate-500">ms</span>
            </div>
            <span class="text-xs text-slate-500">Alvo: Gateway de borda</span>
        </div>

        <div class="p-4 rounded-lg bg-slate-900/90 border border-slate-800">
            <span class="text-xs uppercase tracking-wider text-slate-400 font-medium">Jitter Médio</span>
            <div class="mt-2 flex items-baseline space-x-2">
                <span id="card-jitter" class="text-3xl font-bold text-indigo-400">--</span>
                <span class="text-sm text-slate-500">ms</span>
            </div>
            <span class="text-xs text-slate-500">Variação de atraso</span>
        </div>

        <div class="p-4 rounded-lg bg-slate-900/90 border border-slate-800">
            <span class="text-xs uppercase tracking-wider text-slate-400 font-medium">Perda de Pacotes</span>
            <div class="mt-2 flex items-baseline space-x-2">
                <span id="card-loss" class="text-3xl font-bold text-emerald-400">0%</span>
            </div>
            <span class="text-xs text-slate-500">Status do Enlace</span>
        </div>

        <div class="p-4 rounded-lg bg-slate-900/90 border border-slate-800">
            <span class="text-xs uppercase tracking-wider text-slate-400 font-medium">Topologia Ativa</span>
            <div class="mt-2 flex items-baseline space-x-2">
                <span id="card-nodes" class="text-3xl font-bold text-amber-400">--</span>
                <span class="text-sm text-slate-500">nós</span>
            </div>
            <span class="text-xs text-slate-500">Camadas 1 a 4</span>
        </div>
    </section>

    <main class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        <div class="lg:col-span-2 space-y-6">

            <div class="p-5 rounded-lg bg-slate-900/90 border border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-300">Latência & Jitter (RTT em ms)</h2>
                    <span class="text-xs text-slate-500">Últimos 15 minutos</span>
                </div>
                <div id="chart-rtt" class="w-full flex justify-center min-h-[220px]"></div>
            </div>

            <div class="p-5 rounded-lg bg-slate-900/90 border border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-300">Perda de Pacotes (% Loss)</h2>
                    <span class="text-xs text-slate-500">Alerta se > 2%</span>
                </div>
                <div id="chart-loss" class="w-full flex justify-center min-h-[160px]"></div>
            </div>

        </div>

        <div class="p-5 rounded-lg bg-slate-900/90 border border-slate-800 flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-300">Inventário de Topologia</h2>
                <span class="text-xs text-slate-500">Camada / IP</span>
            </div>

            <div id="topology-list" class="mt-4 space-y-3 flex-1 overflow-y-auto">
                <div class="text-sm text-slate-500 animate-pulse">Carregando dispositivos...</div>
            </div>

            <div class="mt-4 p-3 rounded bg-slate-950/60 border border-slate-800/80 text-xs text-slate-400">
                <em>Esta topologia será o mapa base para o motor de causa provável e o mapa 3D no Passo 11.</em>
            </div>
        </div>

    </main>

    <script>
        const UNIT = 'unidade-teste';
        let uplotRtt = null;
        let uplotLoss = null;

        function initRttChart(width) {
            const opts = {
                width: width,
                height: 220,
                cursor: { drag: { x: true, y: false } },
                scales: { x: { time: true }, y: { auto: true } },
                axes: [
                    { stroke: "#64748b", grid: { stroke: "#1e293b", width: 1 } },
                    { stroke: "#64748b", grid: { stroke: "#1e293b", width: 1 } }
                ],
                series: [
                    {},
                    {
                        label: "RTT (ms)",
                        stroke: "#38bdf8",
                        width: 2,
                        fill: "rgba(56, 189, 248, 0.1)"
                    }
                ]
            };
            return new uPlot(opts, [[], []], document.getElementById("chart-rtt"));
        }

        function initLossChart(width) {
            const opts = {
                width: width,
                height: 160,
                scales: { x: { time: true }, y: { range: [0, 100] } },
                axes: [
                    { stroke: "#64748b", grid: { stroke: "#1e293b", width: 1 } },
                    { stroke: "#64748b", grid: { stroke: "#1e293b", width: 1 }, values: (u, vals) => vals.map(v => v + "%") }
                ],
                series: [
                    {},
                    {
                        label: "Perda (%)",
                        stroke: "#f43f5e",
                        width: 2,
                        fill: "rgba(244, 63, 94, 0.15)"
                    }
                ]
            };
            return new uPlot(opts, [[], []], document.getElementById("chart-loss"));
        }

        async function fetchTopology() {
            try {
                const res = await fetch(`/api/units/${UNIT}/topology`);
                const data = await res.json();

                document.getElementById('card-nodes').innerText = data.nodes.length;
                const list = document.getElementById('topology-list');
                list.innerHTML = '';

                data.nodes.forEach(node => {
                    const layerBadge = {
                        4: 'bg-red-500/10 text-red-400 border-red-500/20',
                        3: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                        2: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        1: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                    }[node.layer] || 'bg-slate-700 text-slate-300';

                    list.innerHTML += `
                        <div class="p-2.5 rounded bg-slate-950/40 border border-slate-800/80 flex items-center justify-between">
                            <div>
                                <div class="text-xs font-semibold text-slate-200">${node.name}</div>
                                <div class="text-[11px] font-mono text-slate-500">${node.ip}</div>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded border ${layerBadge}">L${node.layer}</span>
                        </div>
                    `;
                });
            } catch (err) {
                console.error("Erro ao buscar topologia:", err);
            }
        }

        async function updateMetrics() {
            try {
                const rttRes = await fetch(`/api/metrics/query?unit=${UNIT}&metric=rtt_ms&minutes=15`);
                const rttData = await rttRes.json();

                if (rttData.data && rttData.data.timestamps.length > 0) {
                    const uData = [rttData.data.timestamps, rttData.data.values];
                    uplotRtt.setData(uData);

                    const lastRtt = rttData.data.values[rttData.data.values.length - 1];
                    document.getElementById('card-rtt').innerText = lastRtt.toFixed(1);
                }

                const jitRes = await fetch(`/api/metrics/query?unit=${UNIT}&metric=jitter_ms&minutes=5`);
                const jitData = await jitRes.json();
                if (jitData.data && jitData.data.values.length > 0) {
                    const lastJit = jitData.data.values[jitData.data.values.length - 1];
                    document.getElementById('card-jitter').innerText = lastJit.toFixed(1);
                }

                const lossRes = await fetch(`/api/metrics/query?unit=${UNIT}&metric=loss_pct&minutes=15`);
                const lossData = await lossRes.json();
                if (lossData.data && lossData.data.timestamps.length > 0) {
                    const uDataLoss = [lossData.data.timestamps, lossData.data.values];
                    uplotLoss.setData(uDataLoss);

                    const lastLoss = lossData.data.values[lossData.data.values.length - 1];
                    const lossEl = document.getElementById('card-loss');
                    lossEl.innerText = lastLoss + '%';
                    if (lastLoss > 2) {
                        lossEl.className = "text-3xl font-bold text-rose-500 animate-pulse";
                    } else {
                        lossEl.className = "text-3xl font-bold text-emerald-400";
                    }
                }
            } catch (err) {
                console.error("Erro na atualização de telemetria:", err);
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            const containerWidth = document.getElementById('chart-rtt').parentElement.clientWidth - 40;
            uplotRtt = initRttChart(containerWidth);
            uplotLoss = initLossChart(containerWidth);

            fetchTopology();
            updateMetrics();

            setInterval(updateMetrics, 3000);
        });
    </script>
</body>
</html>
