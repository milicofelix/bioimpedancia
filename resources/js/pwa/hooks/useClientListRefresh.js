import { useEffect, useState } from 'react';
import { apiErrorMessage } from '../../services/bioimpedanceApi';

export default function useClientListRefresh(onRefresh) {
	const [error, setError] = useState('');
	const [attempt, setAttempt] = useState(0);

	useEffect(() => {
		if (!onRefresh) return undefined;
		const controller = new AbortController();
		let pending = false;

		async function refresh() {
			if (pending || document.visibilityState === 'hidden') return;
			pending = true;
			try {
				await onRefresh(controller.signal);
				if (!controller.signal.aborted) setError('');
			} catch (requestError) {
				if (!controller.signal.aborted) setError(apiErrorMessage(requestError));
			} finally {
				pending = false;
			}
		}

		refresh();
		window.addEventListener('focus', refresh);
		window.addEventListener('online', refresh);
		document.addEventListener('visibilitychange', refresh);
		return () => {
			controller.abort();
			window.removeEventListener('focus', refresh);
			window.removeEventListener('online', refresh);
			document.removeEventListener('visibilitychange', refresh);
		};
	}, [onRefresh, attempt]);

	return { refreshError: error, retryRefresh: () => setAttempt((current) => current + 1) };
}
