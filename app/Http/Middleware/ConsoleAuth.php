<?php
/**
 * Guarda do console do Core: valida autenticacao por sessao, credenciais Basic ou redireciona navegadores para /login.
 */

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ConsoleAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            return $next($request);
        }

        $user = $request->getUser();
        $password = $request->getPassword();

        if ($user && $password) {
            $account = User::where('email', $user)
                ->orWhere('email', $user . '@multiguard.local')
                ->first();

            if ($account && Hash::check($password, $account->password)) {
                Auth::login($account);

                return $next($request);
            }

            return response('Credenciais inválidas.', 401, [
                'WWW-Authenticate' => 'Basic realm="MultiGuard Console"',
            ]);
        }

        if ($request->expectsJson() || $request->is('api/*') || app()->runningUnitTests()) {
            return response('Não autenticado.', 401, [
                'WWW-Authenticate' => 'Basic realm="MultiGuard Console"',
            ]);
        }

        return redirect()->guest(route('login'));
    }
}
