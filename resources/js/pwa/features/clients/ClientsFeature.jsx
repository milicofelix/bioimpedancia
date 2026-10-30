import React, { useState } from 'react';
import ClientCreateForm from './ClientCreateForm';
import ClientDetails from './ClientDetails';
import ClientSearchList from './ClientSearchList';
import { canCreateClient } from './clientAccess';

export default function ClientsFeature({ clients, currentUser, scaleModel, enabledModules, onClientCreated, onNewAssessment, onOpenAssistant, onOpenReport }) {
	const [screen, setScreen] = useState('list');
	const [selectedClientId, setSelectedClientId] = useState(null);
	const selectedClient = clients.find((client) => client.id === selectedClientId) ?? null;

	function selectClient(client) {
		setSelectedClientId(client.id);
		setScreen('details');
	}

	function clientCreated(client) {
		onClientCreated(client);
		setSelectedClientId(client.id);
		setScreen('details');
	}

	if (screen === 'create' && canCreateClient(currentUser)) {
		return <ClientCreateForm onCancel={() => setScreen('list')} onCreated={clientCreated} />;
	}

	if (screen === 'details' && selectedClient) {
		return (
			<ClientDetails
				client={selectedClient}
				currentUser={currentUser}
				scaleModel={scaleModel}
				enabledModules={enabledModules}
				onBack={() => setScreen('list')}
				onNewAssessment={onNewAssessment}
				onOpenAssistant={onOpenAssistant}
				onOpenReport={onOpenReport}
			/>
		);
	}

	return (
		<ClientSearchList
			clients={clients}
			canCreate={canCreateClient(currentUser)}
			onCreate={() => setScreen('create')}
			onSelect={selectClient}
		/>
	);
}
