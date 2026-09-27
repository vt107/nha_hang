import './echo';

let audioContext;

/** Tiếng "bíp" ngắn báo có việc mới (trình duyệt chỉ phát sau lần chạm / click đầu tiên trên trang). */
function beep() {
    try {
        audioContext ??= new AudioContext();
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.frequency.value = 880;
        gain.gain.value = 0.15;
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.25);
    } catch {
        // Không có WebAudio: bỏ qua âm thanh.
    }
}

const boundChannels = new Set();

/**
 * Hiện toast + bíp khi event trên kênh private có "alert" (màn hình nhân viên / bếp).
 * Chỉ gắn 1 lần mỗi kênh, kể cả khi chuyển trang bằng wire:navigate.
 */
window.listenForAlerts = (channel) => {
    if (!window.Echo || boundChannels.has(channel)) {
        return;
    }

    boundChannels.add(channel);

    const notify = (event) => {
        if (!event.alert) {
            return;
        }

        window.dispatchEvent(new CustomEvent('toast', { detail: { message: event.alert, type: 'info' } }));
        beep();
    };

    const subscription = window.Echo.private(channel);
    ['.session.updated', '.staff.alert', '.kitchen.updated'].forEach((name) => subscription.listen(name, notify));
};
