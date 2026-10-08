/**
 * Cabecalho do console: nome da unidade, indice de saude 0-100 e estado atual. Compara o horario da ultima
 * avaliacao com o relogio do navegador e, se passar de 30 segundos, avisa que o avaliador (mg:evaluate) parou,
 * porque um indice antigo exibido como atual esconderia justamente a falha mais comum: o monitoramento morto.
 */

import { statusOf } from '../lib/format';

export default function Header({ unitName, health, evaluatedAt }) {
    const style = statusOf(health?.status);
    const age = evaluatedAt ? Math.round((Date.now() - Date.parse(evaluatedAt)) / 1000) : null;
    const stale = age !== null && age > 30;

    return (
        <header className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
            <div>
                <h1 className="text-xl font-bold tracking-wider">MULTI-GUARD</h1>
                <p className="mt-1 text-sm text-slate-400">Unidade: {unitName}</p>
            </div>

            <div className="flex items-center gap-6">
                {!health && <span className="text-sm text-amber-400">Sem avaliação recente. O avaliador está rodando?</span>}
                {health && stale && <span className="text-sm text-amber-400">Avaliador parado há {age}s</span>}

                {health && (
                    <>
                        <div className="text-right">
                            <div className="text-xs uppercase tracking-wider text-slate-400">Estado</div>
                            <div className={`text-lg font-bold ${style.text}`}>{style.label}</div>
                        </div>
                        <div className="text-right">
                            <div className="text-xs uppercase tracking-wider text-slate-400">Índice de saúde</div>
                            <div className={`text-4xl font-bold ${style.text}`}>{health.score ?? '--'}</div>
                        </div>
                    </>
                )}
            </div>
        </header>
    );
}
