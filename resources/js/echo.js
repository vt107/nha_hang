import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Nginx proxy /app/* sang Reverb trên cùng host / cổng với trang web, nên kết nối theo location hiện tại
// (điện thoại truy cập qua IP LAN hay domain đều chạy).
const secure = window.location.protocol === 'https:';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: window.location.port || 80,
    wssPort: window.location.port || 443,
    forceTLS: secure,
    enabledTransports: ['ws', 'wss'],
});
