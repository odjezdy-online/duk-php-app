<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chyba Registrace</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6 mt-10">
            <h1 class="text-2xl font-bold text-red-600 mb-4">Chyba při Registraci</h1>
            <p class="text-gray-700 mb-4">
                Omlouváme se, ale při registraci došlo k chybě. Možné důvody:
            </p>
            <ul class="list-disc list-inside text-gray-700 mb-4">
                <li>Již máte existující účet</li>
                <li>Problém s připojením k Discord API</li>
                <li>Technická chyba systému</li>
            </ul>
            <p class="text-gray-700 mb-4">
                Zkuste to prosím později nebo kontaktujte administrátora.
            </p>
            <div class="flex gap-4">
                <a href="discord_register.php" class="inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Zkusit znovu
                </a>
                <a href="index.php" class="inline-block bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Zpět na hlavní stránku
                </a>
            </div>
        </div>
    </div>
</body>
</html>