import React, { useEffect, useState } from 'react';

const RealtimeNotifications = ({ userId }) => {
    const [notifications, setNotifications] = useState([]);

    const addNotif = (text) => {
        const notifId = Date.now();
        setNotifications(prev => [...prev, { id: notifId, text }]);
        setTimeout(() => {
            setNotifications(prev => prev.filter(n => n.id !== notifId));
        }, 4000);
    };

    useEffect(() => {
        const wsUrl = `wss://api.localhost/ws/${userId}`;
        const ws = new WebSocket(wsUrl);

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                
                if (data.type === 'NEW_RECORD' || data.event === 'new_record') {
                    addNotif(`🏆 ${data.message || 'Новый рекорд!'}`);
                } 
                else if (data.event === 'habit_created') {
                    addNotif(`✨ ${data.message}`);
                    window.dispatchEvent(new CustomEvent('habit-ws-created', { detail: data.habit }));
                } 
                else if (data.event === 'habit_updated') {
                    addNotif(`✏️ ${data.message}`);
                    window.dispatchEvent(new CustomEvent('habit-ws-updated', { detail: data.habit }));
                } 
                else if (data.event === 'habit_deleted') {
                    addNotif(`🗑️ ${data.message}`);
                    window.dispatchEvent(new CustomEvent('habit-ws-deleted', { detail: { id: data.habit_id } }));
                }
            } catch (err) {
                console.error('WS parse error:', err);
            }
        };

        ws.onerror = (error) => console.error('WS error:', error);
        ws.onclose = () => console.log('WS closed');

        return () => ws.close();
    }, [userId]);

    return (
        <div className="fixed top-4 right-4 z-50 space-y-2">
            {notifications.map(notif => (
                <div key={notif.id} className="bg-yellow-400 text-black px-4 py-2 rounded shadow-lg animate-bounce">
                    {notif.text}
                </div>
            ))}
        </div>
    );
};

export default RealtimeNotifications;