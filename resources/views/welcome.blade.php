<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERM Система</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center">

<div class="w-full max-w-xl text-center px-6">
    
    <div class="bg-white rounded-2xl shadow-xl p-10">

        <h1 class="text-4xl font-bold text-gray-900 mb-4">
            ERM Система
        </h1>

        <p class="text-lg text-gray-600 mb-10">
            Балкони та вікна
        </p>

        <div class="flex flex-col gap-4">

            <div class="w-full bg-gray-100 border border-gray-200 rounded-xl px-6 py-4 text-gray-700">
                Для отримання доступу зверніться до адміністратора
            </div>

            <a href="{{ url('/panel/login') }}"
               class="w-full bg-black text-white rounded-xl px-6 py-4 font-medium hover:bg-gray-800 transition">
                Логін
            </a>

        </div>

    </div>

</div>

</body>
</html>