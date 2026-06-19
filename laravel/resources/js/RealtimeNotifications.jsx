import React, { useEffect, useState } from 'react';

const RealtimeNotifications = ({ userId }) => {
    const [notifications, setNotifications] = useState([]);

    useEffect(() => {
        console.log('🚀 React component mounted, userId:', userId);
        const wsUrl = `wss://api.localhost/ws/${userId}`;
        console.log('🔌 Connecting to:', wsUrl);
        const ws = new WebSocket(wsUrl);

        ws.onopen = () => console.log('✅ WebSocket connected');
        
        ws.onerror = (error) => {
        console.error('❌ WebSocket error from React:', error);
        };

        ws.onclose = (event) => {
            console.log('🔌 WebSocket closed from React', event.code, event.reason);
        };

        ws.onmessage = (event) => {
            console.log('📩 ВСЕ сообщение по WS:', event.data);
            
            const data = JSON.parse(event.data);
            console.log('📦 Распарсенные данные:', data); // Смотрим структуру
            
            // Проверяем ОБА варианта ключа и регистра
            if (data.type === 'NEW_RECORD' || data.event === 'new_record') {
                console.log('✅ Событие распознано!');
                
                const notifId = Date.now();
                setNotifications(prev => [...prev, {
                    id: notifId,
                    text: `🏆 ${data.message || 'Новый рекорд!'}`
                }]);
                
                setTimeout(() => {
                    setNotifications(prev => prev.filter(n => n.id !== notifId));
                }, 5000);
            } else {
                console.warn('⚠️ Неизвестный тип события:', data);
            }
        };

        ws.onclose = () => console.log('🔌 WebSocket disconnected');
        
        // Очистка при размонтировании компонента
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