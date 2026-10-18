import { useCallback, useRef } from 'react';

export default function useActionLock() {
	const activeActions = useRef(new Set());

	return useCallback(async (actionName, action) => {
		if (activeActions.current.has(actionName)) return undefined;

		activeActions.current.add(actionName);
		try {
			return await action();
		} finally {
			activeActions.current.delete(actionName);
		}
	}, []);
}
