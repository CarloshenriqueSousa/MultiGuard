/**
 * Hook que consulta uma URL de API em JSON e repete a consulta a cada intervalMs milissegundos (sem intervalo,
 * consulta uma unica vez). Ao trocar a URL descarta o dado anterior e cancela a requisicao em andamento, para
 * que uma resposta atrasada de um alvo antigo nao apareca no grafico do alvo novo. Em caso de erro mantem o
 * ultimo dado recebido e devolve a mensagem em error, que some na proxima resposta valida.
 */

import { useEffect, useState } from 'react';

export function usePolling(url, intervalMs) {
    const [state, setState] = useState({ data: null, error: null });

    useEffect(() => {
        if (!url) {
            return undefined;
        }

        let active = true;
        const controller = new AbortController();

        setState({ data: null, error: null });

        const load = async () => {
            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const body = await response.json();

                if (active) {
                    setState({ data: body, error: null });
                }
            } catch (e) {
                if (active && e.name !== 'AbortError') {
                    setState((previous) => ({ data: previous.data, error: e.message }));
                }
            }
        };

        load();
        const timer = intervalMs ? setInterval(load, intervalMs) : null;

        return () => {
            active = false;
            controller.abort();

            if (timer) {
                clearInterval(timer);
            }
        };
    }, [url, intervalMs]);

    return state;
}
