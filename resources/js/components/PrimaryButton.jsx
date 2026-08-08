import React from 'react';

export default function PrimaryButton({ children, type = 'button', disabled = false }) {
    return (
        <button
            type={type}
            disabled={disabled}
            className="inline-flex w-full items-center justify-center rounded-2xl bg-[#b96f7d] px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-300/40 transition hover:-translate-y-0.5 hover:bg-[#9f5f6b] disabled:cursor-not-allowed disabled:opacity-70 disabled:hover:translate-y-0"
        >
            {children}
        </button>
    );
}
