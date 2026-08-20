<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Дякуємо!</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f5f7; color: #1f2937; display: flex; justify-content: center;
            align-items: center; min-height: 100vh; padding: 24px 16px; text-align: center;
        }
        .card {
            width: 100%; max-width: 480px; background: #fff; border-radius: 16px;
            padding: 36px 24px; box-shadow: 0 4px 24px rgba(0, 0, 0, .08);
        }
        .icon { font-size: 44px; margin-bottom: 12px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { color: #6b7280; margin: 0; font-size: 15px; }
    </style>
</head>
<body>
    <div class="card">
        @if ($alreadyApplied ?? false)
            <div class="icon">ℹ️</div>
            <h1>Ваша заявка вже є в системі</h1>
            <p>Ми вже отримали заявку на вакансію «{{ $vacancy->name }}» з цим номером телефону. Менеджер зв'яжеться з вами найближчим часом — повторно відправляти заявку не потрібно.</p>
        @else
            <div class="icon">✅</div>
            <h1>Дякуємо!</h1>
            <p>Ми отримали вашу заявку на вакансію «{{ $vacancy->name }}». Наш менеджер зв'яжеться з вами найближчим часом.</p>
        @endif
    </div>
</body>
</html>
