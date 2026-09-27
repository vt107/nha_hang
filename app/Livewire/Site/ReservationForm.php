<?php

namespace App\Livewire\Site;

use App\Models\Reservation;
use App\Models\Setting;
use App\Services\Reservations\ReservationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Đặt bàn')]
class ReservationForm extends Component
{
    public string $customer_name = '';

    public string $customer_phone = '';

    public string $customer_email = '';

    public int $party_size = 2;

    public string $date = '';

    public string $time = '19:00';

    public string $note = '';

    public ?string $bookedCode = null;

    public function mount(): void
    {
        $this->date = today()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'regex:/^(0|\+84)[0-9]{9,10}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'party_size' => ['required', 'integer', 'min:1', 'max:'.(int) Setting::get('reservation.max_party_size', 20)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(60)->toDateString()],
            'time' => ['required', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'customer_phone.regex' => 'Số điện thoại không hợp lệ.',
            'party_size.max' => 'Nhóm trên :max người vui lòng gọi điện để đặt bàn.',
            'date.after_or_equal' => 'Vui lòng chọn ngày từ hôm nay.',
            'date.before_or_equal' => 'Chỉ nhận đặt bàn trong vòng 60 ngày.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'customer_name' => 'họ tên',
            'customer_phone' => 'số điện thoại',
            'party_size' => 'số người',
            'date' => 'ngày',
            'time' => 'giờ',
        ];
    }

    public function submit(ReservationService $reservations): void
    {
        $data = $this->validate();

        $reservedAt = Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['time']}");
        $leadMinutes = (int) Setting::get('reservation.min_lead_minutes', 60);

        if ($reservedAt->lt(now()->addMinutes($leadMinutes))) {
            $this->addError('time', "Vui lòng đặt trước ít nhất {$leadMinutes} phút.");

            return;
        }

        $key = 'reservation:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('customer_phone', 'Bạn đã gửi quá nhiều yêu cầu, vui lòng gọi điện cho nhà hàng.');

            return;
        }

        RateLimiter::hit($key, 3600);

        $reservation = $reservations->createFromWeb([
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?: null,
            'party_size' => $data['party_size'],
            'reserved_at' => $reservedAt,
            'duration_minutes' => (int) Setting::get('reservation.default_duration_minutes', 120),
            'note' => $data['note'] ?: null,
        ]);

        $this->bookedCode = $reservation->code;
        $this->reset(['customer_name', 'customer_phone', 'customer_email', 'note']);
    }

    public function render(): View
    {
        return view('livewire.site.reservation-form', [
            'booked' => $this->bookedCode ? Reservation::firstWhere('code', $this->bookedCode) : null,
        ]);
    }
}
