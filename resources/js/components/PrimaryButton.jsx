import React from 'react';

export default function PrimaryButton({ children, type = 'button', disabled = false }) {
    return (
        <button
            type={type}
            disabled={disabled}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/20 transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:translate-y-0"
        >
            {children}
        </button>
    );
}