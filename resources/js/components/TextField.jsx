import React from 'react';

export default function TextField({ label, id, type = 'text', value, onChange, autoComplete, placeholder, error, icon }) {
    return (
        <label className="block space-y-2" htmlFor={id}>
            <span className="text-sm font-medium text-slate-700">{label}</span>
            <span
                className={`flex items-center gap-3 rounded-2xl border bg-white px-4 py-3 shadow-sm transition focus-within:border-[#d88b9a] focus-within:ring-4 focus-within:ring-rose-100 ${
                    error ? 'border-rose-400' : 'border-slate-200'
                }`}
            >
                <span className="text-[#b96f7d]">{icon}</span>
                <input
                    id={id}
                    type={type}
                    value={value}
                    onChange={onChange}
                    autoComplete={autoComplete}
                    placeholder={placeholder}
                    className="w-full border-0 bg-transparent p-0 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                />
            </span>
            {error ? <p className="text-sm text-rose-600">{error}</p> : null}
        </label>
    );
}
