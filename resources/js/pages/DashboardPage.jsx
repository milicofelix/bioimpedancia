import React from 'react';

export default function DashboardPage({ userName }) {
    async function handleLogout() {
        await window.axios.post('/logout', {}, {
            headers: {
                Accept: 'application/json',
            },
        });

        window.location.assign('/login');
    }

    return (
        <main className="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <section className="w-full max-w-3xl rounded-4xl border border-white/70 bg-white/85 p-8 shadow-2xl shadow-slate-300/60 backdrop-blur sm:p-10">
                <span className="inline-flex rounded-full bg-emerald-100 px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">
                    Acesso realizado
                </span>
                <h1 className="mt-6 text-4xl font-semibold tracking-tight text-slate-950">
                    Bem-vindo, {userName}.
                </h1>
                <p className="mt-4 max-w-2xl text-base leading-7 text-slate-600">
                    O login está integrado ao backend Laravel e esta tela já está pronta para receber as próximas features do painel.
                </p>

                <div className="mt-8 flex flex-wrap gap-3">
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/20 transition hover:-translate-y-0.5 hover:bg-slate-800"
                    >
                        Sair
                    </button>
                </div>
            </section>
        </main>
    );
}