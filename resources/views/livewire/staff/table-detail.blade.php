@use('App\Support\Money')
@use('App\Enums\OrderStatus')
@use('App\Enums\OrderItemStatus')
@use('App\Enums\PaymentMethod')

<div x-data="{ picker: false, checkout: false }" x-on:close-picker.window="picker = false" x-on:close-checkout.window="checkout = false">
    <x-staff.shell :title="$diningTable->displayName().' · '.$diningTable->capacity.' ghế'" :back="route('staff.tables')">
        @if ($this->lastInvoice)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
                <div>
                    <p class="font-bold text-emerald-800">Đã thanh toán {{ Money::format($this->lastInvoice->total) }}</p>
                    <p class="text-sm text-emerald-900">
                        Hóa đơn {{ $this->lastInvoice->code }} · {{ $this->lastInvoice->payments->first()?->method->getLabel() }}
                        @if ($change = $this->lastInvoice->payments->first()?->changeAmount())
                            · <strong>Thối lại {{ Money::format($change) }}</strong>
                        @endif
                    </p>
                </div>
                <a href="{{ route('staff.invoices.print', $this->lastInvoice) }}" target="_blank" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">In hóa đơn</a>
            </div>
        @endif

        @if (! $this->tableSession)
            {{-- Bàn trống --}}
            <div class="mx-auto max-w-md space-y-4 py-8 text-center">
                <p class="text-5xl">🪑</p>
                <p class="text-lg font-semibold">Bàn đang trống</p>
                @foreach ($this->reservationsToday as $reservation)
                    <p class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800 ring-1 ring-sky-200">
                        Đặt trước {{ $reservation->reserved_at->format('H:i') }}: {{ $reservation->customer_name }} ({{ $reservation->party_size }} người)
                    </p>
                @endforeach
                @if ($diningTable->is_active)
                    <form wire:submit="openTable" class="flex items-center justify-center gap-2">
                        <label class="text-sm text-stone-600" for="guests">Số khách</label>
                        <input id="guests" type="number" min="1" wire:model="guestCount" class="w-20 rounded-lg border border-stone-300 px-3 py-2 text-center">
                        <button class="rounded-lg bg-amber-600 px-5 py-2 font-semibold text-white">Mở bàn</button>
                    </form>
                @else
                    <p class="text-sm text-red-600">Bàn đang tạm ngưng phục vụ.</p>
                @endif
                <p class="text-xs text-stone-500">Khách cũng có thể tự quét QR trên bàn để mở bàn và gọi món.</p>
            </div>
        @else
            @php($session = $this->tableSession)
            <div class="grid gap-4 lg:grid-cols-[1fr_22rem]">
                <div class="space-y-4">
                    {{-- Thông tin phiên --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-2xl bg-white px-4 py-3 text-sm ring-1 ring-stone-200">
                        <x-status-pill :status="$session->status" />
                        <span>Mở lúc <strong>{{ $session->opened_at->format('H:i') }}</strong> ({{ $session->opened_at->diffForHumans() }})</span>
                        <span>{{ $session->source->getLabel() }}</span>
                        @if ($session->guest_count)
                            <span>{{ $session->guest_count }} khách</span>
                        @endif
                        @if ($session->reservation)
                            <span>Đặt bàn: {{ $session->reservation->customer_name }}</span>
                        @endif
                        <span class="font-mono text-stone-400">{{ $session->code }}</span>
                    </div>

                    {{-- Yêu cầu của khách --}}
                    @foreach ($this->pendingRequests as $request)
                        <div wire:key="req-{{ $request->id }}" class="flex items-center justify-between gap-3 rounded-2xl bg-amber-50 px-4 py-3 ring-1 ring-amber-300">
                            <p class="font-semibold text-amber-900">🔔 {{ $request->type->getLabel() }} <span class="font-normal text-amber-800">· {{ $request->created_at->diffForHumans() }}</span></p>
                            <button type="button" wire:click="resolveRequest({{ $request->id }})" class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white">Đã xử lý</button>
                        </div>
                    @endforeach

                    @if ($this->readyCount > 0)
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-sky-50 px-4 py-3 ring-1 ring-sky-300">
                            <p class="font-semibold text-sky-900">{{ $this->readyCount }} món bếp đã làm xong</p>
                            <button type="button" wire:click="serveAllReady" class="rounded-lg bg-sky-600 px-3 py-1.5 text-sm font-semibold text-white">Đã bưng hết</button>
                        </div>
                    @endif

                    {{-- Orders --}}
                    @forelse ($this->orders as $order)
                        <section wire:key="order-{{ $order->id }}" @class([
                            'overflow-hidden rounded-2xl bg-white ring-1',
                            'ring-2 ring-red-400' => $order->status === OrderStatus::Pending,
                            'ring-stone-200' => $order->status !== OrderStatus::Pending,
                        ])>
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 bg-stone-50 px-4 py-2.5">
                                <div class="text-sm">
                                    <span class="font-semibold">{{ $order->created_at->format('H:i') }}</span>
                                    <span class="text-stone-500">· {{ $order->source->getLabel() }}{{ $order->creator ? ' ('.$order->creator->name.')' : '' }}</span>
                                </div>
                                @if ($order->status === OrderStatus::Pending)
                                    <div class="flex gap-2">
                                        <button type="button"
                                            x-on:click="const r = prompt('Lý do từ chối (khách sẽ thấy):', 'Order không hợp lệ'); if (r !== null) $wire.rejectOrder({{ $order->id }}, r)"
                                            class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm font-semibold text-stone-700">Từ chối</button>
                                        <button type="button" wire:click="confirmOrder({{ $order->id }})" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white">Xác nhận</button>
                                    </div>
                                @else
                                    <x-status-pill :status="$order->status" />
                                @endif
                            </div>
                            @if ($order->note)
                                <p class="border-b border-stone-100 px-4 py-2 text-sm text-stone-600">Ghi chú: “{{ $order->note }}”</p>
                            @endif
                            <ul class="divide-y divide-stone-100">
                                @foreach ($order->items as $item)
                                    <li wire:key="item-{{ $item->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                                        <div class="min-w-0">
                                            <p @class(['font-medium', 'text-stone-400 line-through' => $item->status === OrderItemStatus::Cancelled])>
                                                <span class="font-bold">{{ $item->quantity }}×</span> {{ $item->item_name }}
                                                <span class="text-sm font-normal text-stone-500">{{ Money::format($item->line_total) }}</span>
                                            </p>
                                            @if ($item->note)<p class="text-xs text-stone-500">“{{ $item->note }}”</p>@endif
                                            @if ($item->cancel_reason)<p class="text-xs text-red-600">{{ $item->cancel_reason }}</p>@endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-status-pill :status="$item->status" />
                                            @if ($item->status === OrderItemStatus::Ready)
                                                <button type="button" wire:click="markServed({{ $item->id }})" class="rounded-lg bg-sky-600 px-2.5 py-1 text-xs font-semibold text-white">Đã bưng</button>
                                            @endif
                                            @if ($item->status->canTransitionTo(OrderItemStatus::Cancelled) && $order->status !== OrderStatus::Pending)
                                                <button type="button"
                                                    x-on:click="const r = prompt({{ \Illuminate\Support\Js::from('Lý do hủy '.$item->item_name.':') }}, 'Khách đổi ý'); if (r !== null) $wire.cancelItem({{ $item->id }}, r)"
                                                    class="rounded-lg px-2 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Hủy</button>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @empty
                        <p class="rounded-2xl bg-white py-10 text-center text-stone-500 ring-1 ring-stone-200">Bàn chưa gọi món.</p>
                    @endforelse
                </div>

                {{-- Cột thao tác --}}
                <aside class="space-y-3 lg:sticky lg:top-20 lg:self-start">
                    <button type="button" x-on:click="picker = true" class="w-full rounded-xl bg-stone-900 py-3 font-semibold text-white">+ Gọi món hộ khách</button>
                    <button type="button" x-on:click="checkout = true" class="w-full rounded-xl bg-amber-600 py-3 font-semibold text-white">
                        Thanh toán · {{ Money::format($this->summary->total) }}
                    </button>

                    <div class="rounded-xl bg-white p-3 ring-1 ring-stone-200">
                        <p class="mb-2 text-sm font-semibold">Chuyển bàn</p>
                        <div class="flex gap-2">
                            <select wire:model="transferTo" class="min-w-0 flex-1 rounded-lg border border-stone-300 px-2 py-2 text-sm">
                                <option value="">Chọn bàn trống...</option>
                                @foreach ($this->freeTables as $table)
                                    <option value="{{ $table->id }}">{{ $table->code }} ({{ $table->capacity }} ghế)</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="transfer" wire:confirm="Chuyển khách sang bàn đã chọn?" class="rounded-lg border border-stone-300 px-3 text-sm font-semibold">Chuyển</button>
                        </div>
                    </div>

                    @if ($this->summary->subtotal === 0)
                        <button type="button" wire:click="closeWithoutPayment" wire:confirm="Đóng bàn không thu tiền (khách chưa dùng món nào)?"
                            class="w-full rounded-xl border border-stone-300 py-2.5 text-sm font-semibold text-stone-700">Đóng bàn (không thu tiền)</button>
                    @endif
                </aside>
            </div>

            {{-- Gọi món hộ --}}
            <div x-show="picker" x-cloak class="fixed inset-0 z-40 flex items-end justify-center sm:items-center">
                <div class="absolute inset-0 bg-black/40" x-on:click="picker = false"></div>
                <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col rounded-t-2xl bg-white sm:rounded-2xl">
                    <div class="flex items-center gap-3 border-b border-stone-100 p-4">
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Tìm món..." class="min-w-0 flex-1 rounded-lg border border-stone-300 px-3 py-2">
                        <button type="button" x-on:click="picker = false" class="text-sm font-semibold text-stone-500">Đóng</button>
                    </div>
                    <div class="flex-1 space-y-4 overflow-y-auto p-4">
                        @foreach ($this->menu as $category)
                            <div>
                                <p class="mb-1 text-sm font-bold text-stone-500">{{ $category['name'] }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($category['items'] as $item)
                                        @php($qty = $picked[$item->id] ?? 0)
                                        <div wire:key="pick-{{ $item->id }}" @class(['flex items-center justify-between gap-2 rounded-lg px-3 py-2 ring-1', 'ring-amber-400 bg-amber-50' => $qty, 'ring-stone-200' => ! $qty, 'opacity-40' => ! $item->is_available])>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium">{{ $item->name }}</p>
                                                <p class="text-xs text-stone-500">{{ $item->is_available ? Money::format($item->price) : 'Hết món' }}</p>
                                            </div>
                                            @if ($item->is_available)
                                                <div class="flex shrink-0 items-center gap-1">
                                                    @if ($qty)
                                                        <button type="button" wire:click="pick({{ $item->id }}, -1)" class="size-8 rounded-full bg-stone-100 font-bold">−</button>
                                                        <span class="w-5 text-center font-bold">{{ $qty }}</span>
                                                    @endif
                                                    <button type="button" wire:click="pick({{ $item->id }}, 1)" class="size-8 rounded-full bg-amber-600 font-bold text-white">+</button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="space-y-2 border-t border-stone-100 p-4">
                        <input type="text" wire:model="staffNote" maxlength="500" placeholder="Ghi chú cho bếp (không bắt buộc)" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                        <button type="button" wire:click="submitStaffOrder" @disabled(! $picked) class="w-full rounded-xl bg-amber-600 py-3 font-semibold text-white disabled:opacity-40">
                            Gửi xuống bếp · {{ array_sum($picked) }} món · {{ Money::format($this->pickedTotal) }}
                        </button>
                    </div>
                </div>
            </div>

            {{-- Thanh toán --}}
            <div x-show="checkout" x-cloak class="fixed inset-0 z-40 flex items-end justify-center sm:items-center">
                <div class="absolute inset-0 bg-black/40" x-on:click="checkout = false"></div>
                <div class="relative max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 sm:rounded-2xl">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold">Thanh toán {{ $diningTable->displayName() }}</h2>
                        <button type="button" x-on:click="checkout = false" class="text-sm font-semibold text-stone-500">Đóng</button>
                    </div>

                    @if ($this->unfinishedCount > 0)
                        <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Còn {{ $this->unfinishedCount }} món chưa phục vụ: đánh dấu đã bưng hoặc hủy trước khi thu tiền.</p>
                    @endif

                    @php($summary = $this->summary)
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($summary->lines as $line)
                            <li class="flex justify-between py-1.5"><span>{{ $line['quantity'] }}× {{ $line['name'] }}</span><span>{{ Money::format($line['amount']) }}</span></li>
                        @endforeach
                    </ul>
                    <dl class="mt-2 space-y-1 border-t border-stone-200 pt-2 text-sm">
                        <div class="flex justify-between"><dt>Tạm tính</dt><dd>{{ Money::format($summary->subtotal) }}</dd></div>
                        <div class="flex items-center justify-between gap-3">
                            <dt>Giảm giá</dt>
                            <dd><input type="number" min="0" step="1000" wire:model.live.debounce.400ms="discount" class="w-32 rounded-lg border border-stone-300 px-2 py-1 text-right"></dd>
                        </div>
                        @if ($summary->serviceCharge)<div class="flex justify-between"><dt>Phí phục vụ {{ $summary->serviceChargePercent }}%</dt><dd>{{ Money::format($summary->serviceCharge) }}</dd></div>@endif
                        @if ($summary->vat)<div class="flex justify-between"><dt>VAT {{ $summary->vatPercent }}%</dt><dd>{{ Money::format($summary->vat) }}</dd></div>@endif
                        <div class="flex justify-between pt-1 text-xl font-bold"><dt>Khách trả</dt><dd class="text-amber-700">{{ Money::format($summary->total) }}</dd></div>
                    </dl>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        @foreach (PaymentMethod::cases() as $case)
                            <label @class(['cursor-pointer rounded-xl py-2.5 text-center font-semibold ring-2', 'bg-amber-50 ring-amber-500' => $method === $case->value, 'ring-stone-200' => $method !== $case->value])>
                                <input type="radio" wire:model.live="method" value="{{ $case->value }}" class="sr-only"> {{ $case->getLabel() }}
                            </label>
                        @endforeach
                    </div>

                    @if ($method === PaymentMethod::Cash->value)
                        <div class="mt-3 space-y-2">
                            <label class="block text-sm font-medium">Tiền khách đưa
                                <input type="number" min="0" step="1000" wire:model.live.debounce.400ms="received" placeholder="{{ $summary->total }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-right text-lg">
                            </label>
                            <div class="flex flex-wrap gap-2">
                                @foreach (array_unique([$summary->total, (int) ceil($summary->total / 50000) * 50000, (int) ceil($summary->total / 100000) * 100000, (int) ceil($summary->total / 500000) * 500000]) as $suggest)
                                    <button type="button" wire:click="$set('received', {{ $suggest }})" class="rounded-lg bg-stone-100 px-3 py-1.5 text-sm font-medium">{{ Money::format($suggest) }}</button>
                                @endforeach
                            </div>
                            @if (filled($received) && (int) $received >= $summary->total)
                                <p class="text-lg font-bold text-emerald-700">Thối lại: {{ Money::format((int) $received - $summary->total) }}</p>
                            @elseif (filled($received))
                                <p class="font-semibold text-red-600">Còn thiếu {{ Money::format($summary->total - (int) $received) }}</p>
                            @endif
                        </div>
                    @else
                        <div class="mt-3 space-y-2 text-center">
                            @if ($this->transferQr)
                                <img src="{{ $this->transferQr }}" alt="VietQR" class="mx-auto size-48">
                                <p class="text-xs text-stone-500">Nội dung CK: <span class="font-mono font-semibold">{{ \App\Support\VietQr::cleanDescription($session->code) }}</span></p>
                            @endif
                            <input type="text" wire:model="reference" placeholder="Mã giao dịch (không bắt buộc)" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                            <p class="text-xs text-stone-500">Chỉ xác nhận sau khi đã kiểm tra tiền về tài khoản.</p>
                        </div>
                    @endif

                    <button type="button" wire:click="checkout" wire:loading.attr="disabled" wire:confirm="Xác nhận đã nhận đủ {{ Money::format($summary->total) }}?"
                        @disabled($this->unfinishedCount > 0 || $summary->subtotal === 0)
                        class="mt-4 w-full rounded-xl bg-emerald-600 py-3.5 text-lg font-bold text-white disabled:opacity-40">
                        Xác nhận đã thu tiền
                    </button>
                </div>
            </div>
        @endif
    </x-staff.shell>
</div>
