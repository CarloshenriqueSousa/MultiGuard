/**
 * Tela de login do console Multi-Guard em React com Inertia, estilo escuro e tratamento de erros de credenciais.
 */

import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/login');
    };

    return (
        <>
            <Head title="Login · Multi-Guard" />

            <div className="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 py-12 text-slate-100 selection:bg-sky-500 selection:text-white">
                <div className="w-full max-w-md">
                    <div className="mb-8 text-center">
                        <div className="inline-flex h-12 w-12 items-center justify-center rounded-xl border border-sky-500/30 bg-sky-500/10 text-sky-400">
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h1 className="mt-4 text-2xl font-bold tracking-wider text-slate-100">MULTI-GUARD</h1>
                        <p className="mt-1 text-sm text-slate-400">Console de Monitoramento de Rede</p>
                    </div>

                    <div className="rounded-xl border border-slate-800 bg-slate-900/90 p-8 shadow-2xl backdrop-blur">
                        <form onSubmit={submit} className="space-y-5">
                            {errors.email && (
                                <div className="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-sm text-rose-300">
                                    {errors.email}
                                </div>
                            )}

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Usuário ou E-mail
                                </label>
                                <input
                                    type="text"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="admin ou admin@multiguard.local"
                                    autoFocus
                                    autoComplete="username"
                                    className="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                    Senha
                                </label>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    className="mt-2 block w-full rounded-lg border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-500 transition focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                                    required
                                />
                            </div>

                            <div className="flex items-center justify-between text-sm">
                                <label className="flex items-center gap-2 cursor-pointer text-slate-400 hover:text-slate-300">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="rounded border-slate-700 bg-slate-950 text-sky-500 focus:ring-sky-500/30"
                                    />
                                    <span>Lembrar-me</span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-sky-500 py-2.5 text-sm font-semibold text-white shadow-lg shadow-sky-500/20 transition hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/50 disabled:opacity-50"
                            >
                                {processing ? 'Autenticando...' : 'Entrar no Console'}
                            </button>
                        </form>
                    </div>

                    <p className="mt-6 text-center text-xs text-slate-500">
                        Credencial de laboratório padrão: <code className="text-slate-400">admin</code> / <code className="text-slate-400">admin123</code>
                    </p>
                </div>
            </div>
        </>
    );
}
