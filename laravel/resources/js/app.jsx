import React from 'react';
import { createRoot } from 'react-dom/client';
import RealtimeNotifications from './RealtimeNotifications';

// Находим контейнер на странице
const container = document.getElementById('react-notifications-root');

if (container) {
    const userId = container.dataset.userId;
    const root = createRoot(container);
    root.render(<RealtimeNotifications userId={userId} />);
}