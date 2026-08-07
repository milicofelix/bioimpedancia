import './bootstrap';
import React from 'react';
import { createRoot } from 'react-dom/client';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';

const rootElement = document.getElementById('app');

if (rootElement) {
	const page = rootElement.dataset.page;
	const userName = rootElement.dataset.userName;

	const pageComponent = {
		login: React.createElement(LoginPage),
		dashboard: React.createElement(DashboardPage, { userName }),
	}[page];

	if (pageComponent) {
		createRoot(rootElement).render(React.createElement(React.StrictMode, null, pageComponent));
	}
}
