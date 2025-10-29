<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chyba Registrace - Dopravní Údaje Kolektiv</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-red-50 to-orange-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-red-500 to-orange-600 p-6 text-white text-center">
                <i class="fas fa-exclamation-triangle text-5xl mb-4"></i>
                <h1 class="text-3xl font-bold">Chyba při Registraci</h1>
            </div>
            
            <div class="p-8">
                <div class="text-center mb-6">
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r mb-6">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-times-circle text-xl mr-2"></i>
                            <span class="font-semibold">Registrace se nezdařila</span>
                        </div>
                    </div>
                    
                    <p class="text-gray-700 mb-4 text-lg">
                        Omlouváme se, ale při registraci došlo k chybě.
                    </p>
                    
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-orange-800 mb-2 flex items-center justify-center">
                            <i class="fas fa-question-circle mr-2"></i>
                            Možné důvody:
                        </h3>
                        <ul class="text-left text-sm text-orange-700 space-y-2">
                            <li class="flex items-center">
                                <i class="fas fa-user-times text-red-500 mr-2"></i>
                                Již máte existující účet
                            </li>
                            <li class="flex items-center">
                                <i class="fab fa-discord text-purple-500 mr-2"></i>
                                Problém s připojením k Discord API
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-cogs text-gray-500 mr-2"></i>
                                Technická chyba systému
                            </li>
                        </ul>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-6">
                        Zkuste to prosím později nebo kontaktujte administrátora.
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <a href="discord_register.php" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white text-center py-3 px-4 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 transform hover:scale-105 shadow-md font-semibold">
                        <i class="fas fa-redo mr-2"></i>
                        Zkusit znovu
                    </a>
                    <a href="index.php" class="w-full bg-gradient-to-r from-gray-500 to-gray-600 text-white text-center py-3 px-4 rounded-lg hover:from-gray-600 hover:to-gray-700 transition-all duration-200 transform hover:scale-105 shadow-md font-semibold">
                        <i class="fas fa-home mr-2"></i>
                        Zpět na hlavní stránku
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
