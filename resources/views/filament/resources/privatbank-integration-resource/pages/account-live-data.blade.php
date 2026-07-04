{{--
    Live PrivatBank data block — rendered inside ViewPrivatbankAccount infolist.
    Variables:
      $balance      — array|null   today's balance (turnoverCredit/Debt = today only)
      $periodStats  — array        ['income', 'expense', 'currency'] — summed from all 30-day transactions
      $transactions — array        first 10 of those 30-day transactions (for the table)
      $account      — PrivatbankAccount model
      $apiError     — bool (exception during API call)
--}}

@if ($apiError)
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px 18px;color:#b91c1c;margin-bottom:20px;font-size:14px;">
        ⚠️ Помилка з'єднання з ПриватБанк API. Перевірте налаштування сервера.
    </div>
@elseif ($balance === null)
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px 18px;color:#b91c1c;margin-bottom:20px;font-size:14px;">
        ⚠️ Не вдалося отримати баланс. Перевірте токен та User-Agent для цього акаунту.
    </div>
@else

@php
    $ccy = $balance['CCY'] ?? 'UAH';
@endphp

{{-- ── Рядок 1: поточний баланс + обороти сьогодні ── --}}
<div style="display:flex;gap:12px;margin-bottom:12px;flex-wrap:wrap;">

    <div style="flex:1;min-width:180px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 18px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Баланс (поточний)</div>
        <div style="font-size:24px;font-weight:700;color:#166534;">
            {{ number_format((float)($balance['balanceOut'] ?? 0), 2, ',', ' ') }}
            <span style="font-size:14px;">{{ $ccy }}</span>
        </div>
    </div>

    <div style="flex:1;min-width:150px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Надійшло сьогодні</div>
        <div style="font-size:20px;font-weight:600;color:#1d4ed8;">
            +&nbsp;{{ number_format((float)($balance['turnoverCredit'] ?? 0), 2, ',', ' ') }}
            <span style="font-size:13px;">{{ $ccy }}</span>
        </div>
    </div>

    <div style="flex:1;min-width:150px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px 18px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Списано сьогодні</div>
        <div style="font-size:20px;font-weight:600;color:#c2410c;">
            &minus;&nbsp;{{ number_format((float)($balance['turnoverDebt'] ?? 0), 2, ',', ' ') }}
            <span style="font-size:13px;">{{ $ccy }}</span>
        </div>
    </div>

</div>

{{-- ── Рядок 2: обороти за 30 днів — підраховані з транзакцій (не з /balance API) ── --}}
@php
    $periodIn  = (float)($periodStats['income']   ?? 0);
    $periodOut = (float)($periodStats['expense']  ?? 0);
    $periodNet = $periodIn - $periodOut;
    $periodCcy = $periodStats['currency'] ?? $ccy;
    $period30  = now()->subDays(30)->format('d.m.Y') . ' – ' . now()->format('d.m.Y');
@endphp

<div style="display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;">

    <div style="flex:1;min-width:150px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 18px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Надійшло за 30 днів</div>
        <div style="font-size:20px;font-weight:600;color:#166534;">
            +&nbsp;{{ number_format($periodIn, 2, ',', ' ') }}
            <span style="font-size:13px;">{{ $periodCcy }}</span>
        </div>
        <div style="font-size:11px;color:#9ca3af;margin-top:3px;">{{ $period30 }}</div>
    </div>

    <div style="flex:1;min-width:150px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:14px 18px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Списано за 30 днів</div>
        <div style="font-size:20px;font-weight:600;color:#c2410c;">
            &minus;&nbsp;{{ number_format($periodOut, 2, ',', ' ') }}
            <span style="font-size:13px;">{{ $periodCcy }}</span>
        </div>
        <div style="font-size:11px;color:#9ca3af;margin-top:3px;">{{ $period30 }}</div>
    </div>

    <div style="flex:1;min-width:150px;background:{{ $periodNet >= 0 ? '#eff6ff' : '#fef2f2' }};border:1px solid {{ $periodNet >= 0 ? '#bfdbfe' : '#fecaca' }};border-radius:10px;padding:14px 18px;">
        <div style="font-size:11px;color:#6b7280;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em;">Результат за 30 днів</div>
        <div style="font-size:20px;font-weight:700;color:{{ $periodNet >= 0 ? '#1d4ed8' : '#991b1b' }};">
            {!! $periodNet >= 0 ? '+' : '&minus;' !!}&nbsp;{{ number_format(abs($periodNet), 2, ',', ' ') }}
            <span style="font-size:13px;">{{ $periodCcy }}</span>
        </div>
        <div style="font-size:11px;color:#9ca3af;margin-top:3px;">надходження &minus; списання</div>
    </div>

</div>
@endif

{{-- ───────────── Transactions table ───────────── --}}
<div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">
    Останні транзакції (30 днів)
</div>

@if (empty($transactions))
    <div style="color:#6b7280;font-size:14px;padding:12px 0;">
        @if ($apiError || $balance === null)
            Транзакції недоступні через помилку API.
        @else
            За останні 30 днів транзакцій не знайдено.
        @endif
    </div>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:2px solid #e5e7eb;">
                    <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;white-space:nowrap;">Дата</th>
                    <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Тип</th>
                    <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;white-space:nowrap;">Сума</th>
                    <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Контрагент</th>
                    <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Призначення платежу</th>
                    <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;white-space:nowrap;">№ документа</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transactions as $i => $tx)
                    @php
                        $isCredit  = ($tx['TRANTYPE'] ?? '') === 'C';
                        $amount    = number_format((float)($tx['SUM'] ?? 0), 2, ',', ' ');
                        $currency  = $tx['CCY'] ?? 'UAH';
                        $date      = $tx['DAT_KL'] ?? '—';
                        $partner   = $tx['AUT_CNTR_NAM'] ?? '—';
                        $comment   = $tx['OSND'] ?? '—';
                        $numDoc    = $tx['NUM_DOC'] ?? '—';
                        $rowBg     = ($i % 2 === 0) ? '#ffffff' : '#f9fafb';
                        $amtColor  = $isCredit ? '#166534' : '#991b1b';

                        // Highlight rows with order-number pattern 91xx-yyyy
                        $hasOrderMatch = (bool) preg_match('/91\d{2}-\d+/', $comment);
                        if ($hasOrderMatch) {
                            $rowBg = $isCredit ? '#f0fdf4' : '#fff7ed';
                        }
                    @endphp
                    <tr style="background:{{ $rowBg }};border-bottom:1px solid #f3f4f6;">

                        <td style="padding:7px 10px;white-space:nowrap;color:#374151;">{{ $date }}</td>

                        <td style="padding:7px 10px;">
                            @if ($isCredit)
                                <span style="background:#dcfce7;color:#166534;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;">Прихід</span>
                            @else
                                <span style="background:#fee2e2;color:#991b1b;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;">Списання</span>
                            @endif
                        </td>

                        <td style="padding:7px 10px;text-align:right;font-weight:600;white-space:nowrap;color:{{ $amtColor }};">
                            {{ $isCredit ? '+' : '−' }}&nbsp;{{ $amount }}&nbsp;{{ $currency }}
                        </td>

                        <td style="padding:7px 10px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#374151;" title="{{ $partner }}">
                            {{ $partner }}
                        </td>

                        <td style="padding:7px 10px;max-width:300px;color:#4b5563;">
                            @if ($hasOrderMatch)
                                @php preg_match('/91\d{2}-\d+/', $comment, $m); @endphp
                                <strong style="color:#1d4ed8;">{{ $m[0] }}</strong>{{ Str::after($comment, $m[0]) }}
                            @else
                                {{ Str::limit($comment, 80) }}
                            @endif
                        </td>

                        <td style="padding:7px 10px;white-space:nowrap;color:#6b7280;font-size:12px;">{{ $numDoc }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="font-size:11px;color:#9ca3af;margin-top:10px;">
        Рядки, виділені кольором — містять номер замовлення (шаблон 91XX-YYYY).
        Дані отримані в реальному часі з ПриватBank API при завантаженні сторінки.
    </div>
@endif
