import axios from 'axios';
import { redirectExpiredSession } from './security/browserSession';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

if (csrfToken) {
	window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

window.axios.interceptors.response.use(
	(response) => response,
	async (error) => {
		if ([401, 419].includes(error.response?.status)) {
			await redirectExpiredSession();
		}

		return Promise.reject(error);
	},
);
