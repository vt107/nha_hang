@use('App\Support\Money')

<div>
    <x-customer.shell :session="$this->tableSession" active="bill">
        <div class="space-y-4 px-4 py-4">
            <section class="rounded-2xl bg-white ring-1 ring-stone-200">
                <h2 class="border-b border-stone-100 px-4 py-3 font-bold">Tạm tính</h2>
                @if ($this->summary->lines === [])
                    <p class="px-4 py-8 text-center text-stone-500">Chưa có món nào được xác nhận.</p>
                @else
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($this->summary->lines as $line)
                            <li class="flex justify-between gap-3 px-4 py-2.5">
                                <span><span class="font-semibold">{{ $line['quantity'] }}×</span> {{ $line['name'] }}</span>
                                <span class="shrink-0 font-medium">{{ Money::format($line['amount']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="space-y-1.5 border-t border-stone-200 px-4 py-3 text-sm">
                        <div class="flex justify-between"><dt class="text-stone-600">Tạm tính</dt><dd>{{ Money::format($this->summary->subtotal) }}</dd></div>
                        @if ($this->summary->serviceCharge > 0)
                            <div class="flex justify-between"><dt class="text-stone-600">Phí phục vụ ({{ $this->summary->serviceChargePercent }}%)</dt><dd>{{ Money::format($this->summary->serviceCharge) }}</dd></div>
                        @endif
                        @if ($this->summary->vat > 0)
                            <div class="flex justify-between"><dt class="text-stone-600">VAT ({{ $this->summary->vatPercent }}%)</dt><dd>{{ Money::format($this->summary->vat) }}</dd></div>
                        @endif
                        <div class="flex justify-between pt-1.5 text-lg font-bold"><dt>Tổng cộng</dt><dd class="text-amber-700">{{ Money::format($this->summary->total) }}</dd></div>
                    </dl>
                    <p class="px-4 pb-3 text-xs text-stone-500">Giảm giá (nếu có) sẽ được nhân viên áp dụng khi thanh toán.</p>
                @endif
            </section>

            @if ($this->transfer)
                <section class="rounded-2xl bg-white p-4 text-center ring-1 ring-stone-200">
                    <h2 class="font-bold">Chuyển khoản bằng VietQR</h2>
                    <p class="text-sm text-stone-500">Mở app ngân hàng, quét mã bên dưới</p>
                    <img src="{{ $this->transfer['image'] }}" alt="VietQR" class="mx-auto my-3 size-56 rounded-xl ring-1 ring-stone-200">
                    <dl class="mx-auto max-w-xs space-y-1 text-left text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Ngân hàng</dt><dd class="font-medium">{{ $this->transfer['bank'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Số tài khoản</dt><dd class="font-mono font-medium">{{ $this->transfer['account'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Chủ tài khoản</dt><dd class="text-right font-medium">{{ $this->transfer['name'] }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Số tiền</dt><dd class="font-bold text-amber-700">{{ Money::format($this->summary->total) }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-stone-500">Nội dung</dt><dd class="font-mono font-medium">{{ $this->transfer['description'] }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-stone-500">Sau khi chuyển, bấm “Yêu cầu thanh toán” để nhân viên kiểm tra và xác nhận.</p>
                </section>
            @endif

            @if ($this->summary->total > 0)
                @if ($this->billRequested)
                    <div class="rounded-2xl bg-emerald-50 p-4 text-center text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                        Nhân viên đang tới thanh toán cho bàn của bạn.
                    </div>
                @else
                    <button type="button" wire:click="requestBill" wire:loading.attr="disabled"
                        class="w-full rounded-2xl bg-amber-600 py-3.5 text-base font-bold text-white active:scale-[.98] disabled:opacity-60">
                        Yêu cầu thanh toán
                    </button>
                    <p class="text-center text-xs text-stone-500">Thanh toán tiền mặt hoặc chuyển khoản, nhân viên sẽ tới bàn.</p>
                @endif
            @endif
        </div>
    </x-customer.shell>
</div>
