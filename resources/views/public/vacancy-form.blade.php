<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $vacancy->name }} — вакансія</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f5f7;
            color: #1f2937;
            display: flex;
            justify-content: center;
            padding: 24px 16px;
        }
        .card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
        }
        h1 { font-size: 22px; margin: 0 0 6px; }
        p.lead { color: #6b7280; margin: 0 0 24px; font-size: 15px; }
        label { display: block; font-weight: 600; font-size: 14px; margin: 18px 0 6px; }
        input[type=text], input[type=tel] {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 10px; font-size: 16px;
        }
        .skills { display: flex; flex-direction: column; gap: 10px; margin-top: 6px; }
        .skills label { display: flex; align-items: center; gap: 10px; font-weight: 400; margin: 0; font-size: 15px; }
        .skills input { width: 18px; height: 18px; flex-shrink: 0; }
        textarea {
            width: 100%; padding: 12px 14px; border: 1px solid #d1d5db;
            border-radius: 10px; font-size: 15px; min-height: 70px; font-family: inherit;
        }
        button {
            width: 100%; margin-top: 24px; padding: 15px; border: none;
            border-radius: 10px; background: #16a34a; color: #fff;
            font-size: 17px; font-weight: 700; cursor: pointer;
        }
        button:active { background: #15803d; }
        .error { color: #dc2626; font-size: 13px; margin: 6px 0 0; }
        /* Honeypot: present in the DOM for bots that fill every field, but
           invisible and unreachable by keyboard for real visitors. */
        .hp { position: absolute; left: -9999px; top: -9999px; }
        .closed { text-align: center; padding: 20px 0; color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        @if (! $vacancy->is_active)
            <h1>{{ $vacancy->name }}</h1>
            <div class="closed">Наразі набір на цю вакансію призупинено. Дякуємо за інтерес — спробуйте пізніше.</div>
        @else
            <h1>{{ $vacancy->name }}</h1>
            <p class="lead">Залиште номер телефону — передзвонимо і розкажемо про умови.</p>

            <form method="POST" action="{{ route('vacancy-form.store', $vacancy->slug) }}" novalidate>
                @csrf

                <div class="hp" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <label for="first_name">Ім'я *</label>
                <input type="text" id="first_name" name="first_name" required value="{{ old('first_name') }}">
                @error('first_name') <p class="error">{{ $message }}</p> @enderror

                <label for="phone">Номер телефону *</label>
                <input type="tel" id="phone" name="phone" required value="{{ old('phone') }}" placeholder="+380...">
                @error('phone') <p class="error">{{ $message }}</p> @enderror

                <label>Досвід (не обов'язково)</label>
                <div class="skills">
                    @foreach ($skillOptions as $key => $label)
                        <label>
                            <input type="checkbox" name="skills[]" value="{{ $key }}"
                                @checked(in_array($key, old('skills', [])))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <label for="skills_other">Інше</label>
                <textarea id="skills_other" name="skills_other" placeholder="Якщо є щось, що варто додати">{{ old('skills_other') }}</textarea>

                <button type="submit">Передзвоніть мені</button>
            </form>
        @endif
    </div>
</body>
</html>
