import React, { useMemo, useState } from 'react';
import TextField from '../components/TextField';
import PrimaryButton from '../components/PrimaryButton';

const defaultErrors = {
    email: null,
    password: null,
};

function normalizeErrors(responseErrors) {
    return {
        email: responseErrors?.email?.[0] ?? null,
        password: responseErrors?.password?.[0] ?? null,
    };
}

export default function LoginPage() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState(defaultErrors);
    const [status, setStatus] = useState('');

    const canSubmit = useMemo(() => email.trim() !== '' && password.trim() !== '', [email, password]);

    async function handleSubmit(event) {
        event.preventDefault();

        setProcessing(true);
        setErrors(defaultErrors);
        setStatus('');

        try {
            const response = await window.axios.post(
                '/login',
                {
                    email,
                    password,
                    remember,
                },
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            window.location.assign(response.data.redirectTo);
        } catch (error) {
            if (error.response?.status === 422) {
                setErrors(normalizeErrors(error.response.data.errors));
                setStatus(error.response.data.message ?? 'Corrija os campos abaixo e tente novamente.');
                return;
            }

            setStatus('Não foi possível autenticar agora. Tente novamente em instantes.');
        } finally {
            setProcessing(false);
        }
    }

    return (
        <main className="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
            <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top,rgba(255,255,255,0.92),rgba(241,245,249,0.8))]" />
            <div className="absolute left-0 top-0 -z-10 h-72 w-72 rounded-full bg-orange-200/40 blur-3xl" />
            <div className="absolute bottom-0 right-0 -z-10 h-80 w-80 rounded-full bg-sky-200/40 blur-3xl" />

            <section className="grid w-full max-w-6xl gap-8 lg:grid-cols-[1.1fr_0.9fr]">
                <div className="flex flex-col justify-between rounded-4xl border border-white/70 bg-slate-950 p-8 text-white shadow-2xl shadow-slate-300/60 sm:p-10 lg:p-12">
                    <div className="space-y-6">
                        <span className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-slate-200">
                            Ricostye Magrecimento
                        </span>
                        <div className="space-y-4">
                            <h1 className="max-w-xl text-4xl font-semibold tracking-tight sm:text-5xl">
                                Acesso rápido, interface limpa e fluxo de autenticação consistente.
                            </h1>
                            <p className="max-w-lg text-base leading-7 text-slate-300">
                                Esta tela foi construída com React no frontend e Laravel cuidando da sessão, validação e segurança.
                            </p>
                        </div>
                    </div>

                    <div className="mt-10 grid gap-4 sm:grid-cols-3">
                        {[
                            ['Sessão segura', 'CSRF + sessão + redirect intended'],
                            ['UI reativa', 'Estados claros e feedback imediato'],
                            ['Código limpo', 'Componentes pequenos e responsabilidades separadas'],
                        ].map(([title, description]) => (
                            <article key={title} className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                                <h2 className="text-sm font-semibold text-white">{title}</h2>
                                <p className="mt-1 text-sm leading-6 text-slate-300">{description}</p>
                            </article>
                        ))}
                    </div>
                </div>

                <div className="flex items-center">
                    <div className="w-full rounded-4xl border border-white/80 bg-white/90 p-6 shadow-2xl shadow-slate-300/70 backdrop-blur sm:p-8 lg:p-10">
                        <div className="mb-8 space-y-2">
                            <h2 className="text-3xl font-semibold tracking-tight text-slate-950">Entrar na conta</h2>
                            <p className="text-sm leading-6 text-slate-600">
                                Use suas credenciais para acessar o painel.
                            </p>
                        </div>

                        {status ? (
                            <div className="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                                {status}
                            </div>
                        ) : null}

                        <form className="space-y-5" onSubmit={handleSubmit}>
                            <TextField
                                label="E-mail"
                                id="email"
                                type="email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                autoComplete="email"
                                placeholder="voce@exemplo.com"
                                error={errors.email}
                                icon={
                                    <svg aria-hidden="true" viewBox="0 0 24 24" className="h-5 w-5">
                                        <path
                                            d="M4 6.75A2.75 2.75 0 0 1 6.75 4h10.5A2.75 2.75 0 0 1 20 6.75v10.5A2.75 2.75 0 0 1 17.25 20H6.75A2.75 2.75 0 0 1 4 17.25V6.75Zm2.34-.25 5.16 4.29a1 1 0 0 0 1.3 0L18.16 6.5H6.34Zm11.91 2.03-4.7 3.9a3 3 0 0 1-3.9 0l-4.7-3.9v8.72c0 .41.34.75.75.75h11.8c.41 0 .75-.34.75-.75V8.53Z"
                                            fill="currentColor"
                                        />
                                    </svg>
                                }
                            />

                            <TextField
                                label="Senha"
                                id="password"
                                type="password"
                                value={password}
                                onChange={(event) => setPassword(event.target.value)}
                                autoComplete="current-password"
                                placeholder="Sua senha"
                                error={errors.password}
                                icon={
                                    <svg aria-hidden="true" viewBox="0 0 24 24" className="h-5 w-5">
                                        <path
                                            d="M12 2a5.5 5.5 0 0 0-5.5 5.5V10H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-.5V7.5A5.5 5.5 0 0 0 12 2Zm-3.5 5.5a3.5 3.5 0 1 1 7 0V10h-7V7.5Z"
                                            fill="currentColor"
                                        />
                                    </svg>
                                }
                            />

                            <label className="flex items-center gap-3 text-sm text-slate-600">
                                <input
                                    type="checkbox"
                                    checked={remember}
                                    onChange={(event) => setRemember(event.target.checked)}
                                    className="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                                />
                                Lembrar de mim
                            </label>

                            <PrimaryButton type="submit" disabled={processing || !canSubmit}>
                                {processing ? 'Entrando...' : 'Entrar'}
                            </PrimaryButton>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    );
}