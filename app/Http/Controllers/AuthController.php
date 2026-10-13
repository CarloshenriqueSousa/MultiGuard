<?php
/**
 * Controlador de autenticacao do console interno: login por formulario web, encerramento de sessao e redirecionamento.
 */

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function showLoginForm(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return Inertia::render('Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = strtolower(trim($credentials['email']));
        $user = User::where('email', $login)
            ->orWhere('email', $login . '@multiguard.local')
            ->first();

        if ($user && Auth::attempt(['email' => $user->email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Credenciais inválidas. Verifique o usuário e a senha.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
