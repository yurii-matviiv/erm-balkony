{{--
    Historical payment rows for this order — synced from old CRM's
    `orders_payments` via OrderPaymentsSyncMapper. Read-only display;
    new payments will come from the future Рахунки/Оплати module.

    Variables passed from EditOrder's Placeholder closure:
    - $mainPayments  — Collection of OrderPayment (category != 'salary'), ordered by paid_at
    - $salaryPayments — Collection of OrderPayment (category = 'salary')
--}}
@php
    use App\Models\OrderPayment;

    $methodLabels   = OrderPayment::paymentMethodOptions();
    $payerLabels    = OrderPayment::payerTypeOptions();

    $statusStyle = [
        'received' => ['label' => 'Отримано',   'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'],
        'sent'     => ['label' => 'Надіслано',  'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'],
        'pending'  => ['label' => 'Очікується', 'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'],
    ];
@endphp

@if ($mainPayments->isEmpty() && $salaryPayments->isEmpty())
    <p class="text-sm text-gray-400 italic py-1">Оплат не знайдено — можливо, синхронізацію ще не запущено.</p>
@else

    {{-- ── Main payments table ─────────────────────────────────────────── --}}
    @if ($mainPayments->isNotEmpty())
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        <th class="px-3 py-2 text-left font-medium">Дата</th>
                        <th class="px-3 py-2 text-left font-medium">Напрямок</th>
                        <th class="px-3 py-2 text-left font-medium">Хто</th>
                        <th class="px-3 py-2 text-right font-medium">Сума</th>
                        <th class="px-3 py-2 text-left font-medium">Метод</th>
                        <th class="px-3 py-2 text-left font-medium">Статус</th>
                        <th class="px-3 py-2 text-left font-medium">Коментар</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($mainPayments as $payment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">

                            {{-- Date --}}
                            <td class="px-3 py-2 whitespace-nowrap text-gray-500 dark:text-gray-400">
                                {{ $payment->paid_at?->format('d.m.Y') ?? '—' }}
                            </td>

                            {{-- Direction badge --}}
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if ($payment->direction === 'income')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                        ↓ Дохід
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                        ↑ Витрата
                                    </span>
                                @endif
                            </td>

                            {{-- Payer type + name --}}
                            <td class="px-3 py-2">
                                <div class="text-gray-700 dark:text-gray-300 font-medium text-xs">
                                    {{ $payerLabels[$payment->payer_type] ?? $payment->payer_type }}
                                </div>
                                @if ($payment->payer_name)
                                    <div class="text-gray-400 text-xs">{{ $payment->payer_name }}</div>
                                @endif
                            </td>

                            {{-- Amount --}}
                            <td class="px-3 py-2 text-right whitespace-nowrap font-semibold {{ $payment->direction === 'income' ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                {{ number_format($payment->amount, 0, '', ' ') }} грн
                            </td>

                            {{-- Method --}}
                            <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                {{ $methodLabels[$payment->payment_method] ?? ($payment->payment_method ?? '—') }}
                            </td>

                            {{-- Status --}}
                            <td class="px-3 py-2 text-xs whitespace-nowrap">
                                @if (isset($statusStyle[$payment->status]))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded font-medium {{ $statusStyle[$payment->status]['class'] }}">
                                        {{ $statusStyle[$payment->status]['label'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">{{ $payment->status ?? '—' }}</span>
                                @endif
                            </td>

                            {{-- Comment --}}
                            <td class="px-3 py-2 text-gray-400 dark:text-gray-500 text-xs max-w-xs truncate" title="{{ $payment->comment }}">
                                {{ $payment->comment ?? '' }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── Salary rows (collapsed by default — not part of the main flow) ── --}}
    @if ($salaryPayments->isNotEmpty())
        <details class="mt-3 text-sm">
            <summary class="cursor-pointer text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 select-none">
                Зарплатні нарахування — {{ $salaryPayments->count() }} рядк{{ $salaryPayments->count() === 1 ? 'ок' : ($salaryPayments->count() < 5 ? 'и' : 'ів') }},
                {{ number_format($salaryTotal, 0, '', ' ') }} грн
            </summary>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 mt-2">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            <th class="px-3 py-2 text-left font-medium">Дата</th>
                            <th class="px-3 py-2 text-left font-medium">Хто</th>
                            <th class="px-3 py-2 text-right font-medium">Сума</th>
                            <th class="px-3 py-2 text-left font-medium">Коментар</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($salaryPayments as $payment)
                            <tr>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">
                                    {{ $payment->paid_at?->format('d.m.Y') ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                    {{ $payerLabels[$payment->payer_type] ?? $payment->payer_type }}
                                    @if ($payment->payer_name) — {{ $payment->payer_name }}@endif
                                </td>
                                <td class="px-3 py-2 text-right font-semibold text-blue-700 dark:text-blue-400">
                                    {{ number_format($payment->amount, 0, '', ' ') }} грн
                                </td>
                                <td class="px-3 py-2 text-gray-400 text-xs">{{ $payment->comment ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

@endif
