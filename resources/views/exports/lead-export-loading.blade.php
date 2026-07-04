<!DOCTYPE html>
<html lang="uk">
<head>

    <meta charset="UTF-8">

    <title>Готуємо CSV-експорт…</title>

    <style>

        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: white;
            font-family: Arial, sans-serif;
        }

        .wrapper {
            text-align: center;
            max-width: 500px;
            padding: 30px;
        }

        .spinner {
            width: 64px;
            height: 64px;
            border: 5px solid rgba(255,255,255,0.15);
            border-top-color: #facc15;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        h1 {
            margin: 0 0 14px;
            font-size: 24px;
            font-weight: 700;
        }

        p {
            margin: 0;
            opacity: 0.75;
            line-height: 1.6;
            font-size: 15px;
        }

    </style>

</head>

<body>

<div class="wrapper">

    <div class="spinner"></div>

    <h1>Готуємо CSV-експорт…</h1>

    <p>
        Зачекайте, будь ласка — браузер формує та завантажує CSV-файл.
        <br><br>
        Це вікно закриється автоматично за кілька секунд.
    </p>

</div>

<script>

    setTimeout(() => {

        window.location.href =
            "{{ route('lead-export.csv') }}"
            + "?preset={{ $preset }}"
            + "&date_from={{ $date_from }}"
            + "&date_to={{ $date_to }}";

    }, 300);

    setTimeout(() => {

        window.close();

    }, 5000);

</script>

</body>
</html>
