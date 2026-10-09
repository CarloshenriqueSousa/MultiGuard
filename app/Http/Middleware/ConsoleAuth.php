<?php
/**
 * Guarda do console do Core: exige usuario interno por HTTP Basic sem sessao (Auth::onceBasic) em todas as paginas
 * e na API de leitura. A ingestao nao passa por aqui, ela usa o token de unidade (middleware ingest). Credencial
 * ausente ou errada devolve 401 com o cabecalho que faz o navegador pedir usuario e senha.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsoleAuth
{
    public function handle(Request $request, Closure $next)
    {
        return Auth::onceBasic() ?: $next($request);
    }
}
