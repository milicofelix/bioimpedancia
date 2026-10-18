import React, { useRef, useState } from 'react';
import PwaIcon from './PwaIcon';

const variants = {
	primary: 'pwa-button-primary',
	secondary: 'pwa-button-secondary',
	ghost: 'min-h-11 rounded-xl text-ricosty-700 font-bold hover:bg-ricosty-50',
	danger: 'min-h-11 rounded-xl border border-rose-200 bg-white font-bold text-rose-700 hover:bg-rose-50',
};

const PwaButton = React.forwardRef(function PwaButton({ icon, variant = 'primary', className = '', children, disabled = false, loading = false, onClick, ...props }, reference) {
	const clickLocked = useRef(false);
	const [waitingForClick, setWaitingForClick] = useState(false);
	const busy = loading || waitingForClick;

	function handleClick(event) {
		if (!onClick || clickLocked.current || disabled || busy) return;

		clickLocked.current = true;
		let result;
		try {
			result = onClick(event);
		} catch (error) {
			clickLocked.current = false;
			throw error;
		}

		if (!result || typeof result.then !== 'function') {
			clickLocked.current = false;
			return;
		}

		setWaitingForClick(true);
		Promise.resolve(result).then(
			() => {
				clickLocked.current = false;
				setWaitingForClick(false);
			},
			() => {
				clickLocked.current = false;
				setWaitingForClick(false);
			},
		);
	}

	return <button ref={reference} disabled={disabled || busy} aria-busy={busy || undefined} onClick={onClick ? handleClick : undefined} className={`pwa-focus pwa-pressable inline-flex min-h-11 min-w-11 touch-manipulation items-center justify-center gap-2 px-4 text-sm disabled:cursor-not-allowed disabled:opacity-50 ${variants[variant]} ${className}`} {...props}>{busy ? <PwaIcon name="refresh" size={18} className="animate-spin" /> : icon ? <PwaIcon name={icon} size={18} /> : null}{children}</button>;
});

export default PwaButton;
