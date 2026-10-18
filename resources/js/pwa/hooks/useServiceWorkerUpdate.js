import { useEffect, useState } from 'react';
import { activateOperationalUpdate, PWA_UPDATE_AVAILABLE_EVENT } from '../services/registerServiceWorker';

export default function useServiceWorkerUpdate() {
	const [available, setAvailable] = useState(false);
	const [applying, setApplying] = useState(false);

	useEffect(() => {
		const handleAvailable = () => setAvailable(true);
		window.addEventListener(PWA_UPDATE_AVAILABLE_EVENT, handleAvailable);

		return () => window.removeEventListener(PWA_UPDATE_AVAILABLE_EVENT, handleAvailable);
	}, []);

	async function apply() {
		setApplying(true);
		try {
			const activated = await activateOperationalUpdate();
			if (!activated) setApplying(false);
		} catch {
			setApplying(false);
		}
	}

	return {
		available,
		applying,
		apply,
		dismiss: () => setAvailable(false),
	};
}
