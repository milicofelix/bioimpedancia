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
        <main className="flex min-h-screen items-center justify-center bg-[#fbf7fa] px-4 py-10 text-slate-900 sm:px-6 lg:px-8">
            <section className="grid w-full max-w-6xl overflow-hidden rounded-[2rem] border border-rose-100 bg-white shadow-2xl shadow-rose-200/40 lg:grid-cols-[1fr_0.9fr]">
                <div className="flex min-h-[420px] flex-col justify-between bg-gradient-to-br from-[#f8dfe8] via-white to-[#eee6fb] p-7 sm:p-10 lg:p-12">
                    <div>
                        <img src="/images/brand/ricosty-logo.png" alt="Ricosty Emagrecimento e Estética" className="h-auto w-full max-w-[520px] object-contain" />
                    </div>

                    <div className="mt-10 max-w-xl">
                        <p className="text-xs font-bold uppercase tracking-[0.24em] text-[#b96f7d]">Ricosty Emagrecimento e Estética</p>
                        <h1 className="mt-4 text-3xl font-semibold leading-tight text-[#3f3f46] sm:text-4xl">
                            Sistema profissional de avaliação corporal
                        </h1>
                        <p className="mt-4 text-base leading-7 text-slate-600">
                            Acesse o painel para registrar avaliações, acompanhar evolução e gerar relatórios de bioimpedância.
                        </p>
                    </div>
                </div>

                <div className="flex items-center bg-white">
                    <div className="w-full p-6 sm:p-8 lg:p-10">
                        <div className="mb-8 space-y-2">
                            <h2 className="text-3xl font-semibold tracking-tight text-[#3f3f46]">Entrar na conta</h2>
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
                                    className="h-4 w-4 rounded border-rose-200 text-[#b96f7d] focus:ring-rose-300"
                                />
                                Manter conectado neste dispositivo
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
