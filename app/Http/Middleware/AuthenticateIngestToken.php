<?php
/**
* A unidade vem do token, nunca do corpo da requisição. Um agente não consegue gravar na unidade de outro, mesmo que tente.
*/
namespace App\Http\Middleware;

use App\Models\IngestToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        abort_if(! $plain, 401, 'Token ausente');

        $token = IngestToken::with('unit')
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();

        abort_if(! $token, 401, 'Token inválido');

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('unit', $token->unit);

        return $next($request);
    }
}
