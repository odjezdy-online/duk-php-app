<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrace Úspěšná - Dopravní Údaje Kolektiv</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-500 to-blue-600 p-6 text-white text-center">
                <i class="fas fa-check-circle text-5xl mb-4"></i>
                <h1 class="text-3xl font-bold">Registrace Úspěšná!</h1>
            </div>
            
            <div class="p-8">
                <div class="text-center mb-6">
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r mb-6">
                        <div class="flex items-center justify-center">
                            <i class="fas fa-check-circle text-xl mr-2"></i>
                            <span class="font-semibold">Registrace proběhla úspěšně</span>
                        </div>
                    </div>
                    
                    <p class="text-gray-700 mb-4 text-lg">
                        Vaše registrace byla úspěšně přijata a nyní čeká na schválení administrátorem.
                    </p>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-blue-800 mb-2 flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Co se stane dál?
                        </h3>
                        <ul class="text-left text-sm text-blue-700 space-y-2">
                            <li class="flex items-center">
                                <i class="fas fa-clock text-blue-500 mr-2"></i>
                                Administrátor zkontroluje vaši žádost
                            </li>
                            <li class="flex items-center">
                                <i class="fab fa-discord text-purple-500 mr-2"></i>
                                Obdržíte oznámení na Discord po schválení
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-bus text-green-500 mr-2"></i>
                                Budete moci přispívat do databáze autobusů
                            </li>
                        </ul>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-6">
                        Děkujeme za váš zájem o naši komunitu!
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <a href="index.php" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 text-white text-center py-3 px-4 rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all duration-200 transform hover:scale-105 shadow-md font-semibold">
                        <i class="fas fa-home mr-2"></i>
                        Zpět na hlavní stránku
                    </a>
                    <a href="login.php" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white text-center py-3 px-4 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-200 transform hover:scale-105 shadow-md font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Přihlásit se
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
