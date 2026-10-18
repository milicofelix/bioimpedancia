import React from 'react';
import PwaIcon from './PwaIcon';

const variants = {
	primary: 'pwa-button-primary',
	secondary: 'pwa-button-secondary',
	ghost: 'min-h-11 rounded-xl text-ricosty-700 font-bold hover:bg-ricosty-50',
	danger: 'min-h-11 rounded-xl border border-rose-200 bg-white font-bold text-rose-700 hover:bg-rose-50',
};

const PwaButton = React.forwardRef(function PwaButton({ icon, variant = 'primary', className = '', children, ...props }, reference) {
	return <button ref={reference} className={`pwa-focus pwa-pressable inline-flex min-h-11 min-w-11 touch-manipulation items-center justify-center gap-2 px-4 text-sm disabled:cursor-not-allowed disabled:opacity-50 ${variants[variant]} ${className}`} {...props}>{icon ? <PwaIcon name={icon} size={18} /> : null}{children}</button>;
});

export default PwaButton;
