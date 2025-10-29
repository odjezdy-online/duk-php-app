<?php
// Configure session parameters before starting the session
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',  // Current domain
    'secure' => true,  // Only send cookie over HTTPS
    'httponly' => true // Protect against XSS
]);

session_start();
date_default_timezone_set('Europe/Prague');

// Load database configuration
require_once 'config.php';

// Check if the user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("Location: login.php");
    exit;
}

// Load users data
$users = [];
if (file_exists('users.json')) {
    $users = json_decode(file_get_contents('users.json'), true);
}

// Check if user is an approved Discord user or admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isApprovedDiscordUser = false;

if (isset($_SESSION['discord_id'])) {
    $isApprovedDiscordUser = isset($users['approved'][$_SESSION['discord_id']]);
}

// If user is neither admin nor approved Discord user, redirect to login
if (!$isAdmin && !$isApprovedDiscordUser) {
    $_SESSION['error'] = 'Nemáte oprávnění pro přístup do administrace.';
    header("Location: login.php");
    exit;
}

// Handle user approval/rejection via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_id']) && !isset($_POST['vhc_id'])) {
    $response = ['success' => false, 'message' => ''];
    $userId = $_POST['user_id'];

    if ($_POST['action'] === 'approve' && isset($users['pending'][$userId])) {
        try {
            // Načtení API secret z .env souboru
            $botConfig = parse_ini_file(__DIR__ . '/discord_bot/.env');
            $apiSecret = 'you_fucking_b1tch!_go_ky$';

            // Komunikace s Discord botem
            $ch = curl_init('http://127.0.0.1:5008/verify');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'user_id' => $userId,
                'username' => $users['pending'][$userId]['username'],
                'action' => 'approve',
                'api_secret' => $apiSecret
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Secret: ' . $apiSecret
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $botResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                // Update user status
                // Load existing bus data
                $busData = [];
                if (file_exists('bus_data.json')) {
                    $busData = json_decode(file_get_contents('bus_data.json'), true) ?: [];
                }

                // Update users data
                $users['approved'][$userId] = $users['pending'][$userId];
                unset($users['pending'][$userId]);
                file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));

                // Save bus data back
                if (!empty($busData)) {
                    file_put_contents('bus_data.json', json_encode($busData, JSON_PRETTY_PRINT));
                }
                
                $response['success'] = true;
                $response['message'] = 'Uživatel byl úspěšně schválen.';
            } else {
                throw new Exception('Discord bot returned error: ' . $botResponse);
            }
        } catch (Exception $e) {
            error_log('User approval failed: ' . $e->getMessage());
            $response['message'] = 'Nepodařilo se schválit uživatele: ' . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'reject' && isset($users['pending'][$userId])) {
        try {
            // Načtení API secret z .env souboru
            $botConfig = parse_ini_file(__DIR__ . '/discord_bot/.env');
            $apiSecret = $botConfig['API_SECRET'] ?? '';

            // Komunikace s Discord botem pro zamítnutí
            $ch = curl_init('http://127.0.0.1:5008/verify');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'user_id' => $userId,
                'username' => $users['pending'][$userId]['username'],
                'action' => 'reject',
                'api_secret' => $apiSecret
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Secret: ' . $apiSecret
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $botResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                // Load existing bus data
                $busData = [];
                if (file_exists('bus_data.json')) {
                    $busData = json_decode(file_get_contents('bus_data.json'), true) ?: [];
                }

                // Update users data
                unset($users['pending'][$userId]);
                file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));

                // Save bus data back
                if (!empty($busData)) {
                    file_put_contents('bus_data.json', json_encode($busData, JSON_PRETTY_PRINT));
                }
                
                $response['success'] = true;
                $response['message'] = 'Uživatel byl zamítnut.';
            } else {
                throw new Exception('Discord bot returned error: ' . $botResponse);
            }
        } catch (Exception $e) {
            error_log('User rejection failed: ' . $e->getMessage());
            $response['message'] = 'Nepodařilo se zamítnout uživatele: ' . $e->getMessage();
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    $vhcId = filter_input(INPUT_POST, 'vhc_id', FILTER_SANITIZE_NUMBER_INT);
    $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);
    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);

    if ($vhcId) {
        // Check if entry already exists and user has permission to edit
        if (isset($busData[$vhcId])) {
            $existingEntry = $busData[$vhcId];
            $currentUserId = $_SESSION['discord_id'] ?? null;
            
            // Check if current user is the original creator or an admin
            if ($existingEntry['discord_id'] !== $currentUserId && !isset($_SESSION['is_admin'])) {
                $response['message'] = 'Nemáte oprávnění upravit tento záznam.';
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }

        $username = $_SESSION['discord_id'] ? 
            ($users['approved'][$_SESSION['discord_id']]['username'] ?? $_SESSION['username']) : 
            $_SESSION['username'];

        // Load existing bus data
        $existingBusData = [];
        if (file_exists('bus_data.json')) {
            $existingBusData = json_decode(file_get_contents('bus_data.json'), true) ?: [];
        }

        // Add or update the entry
        $existingBusData[$vhcId] = [
            'image' => $image,
            'url' => $url,
            'added_by' => $username,
            'discord_id' => $_SESSION['discord_id'] ?? null,
            'added_at' => date('Y-m-d H:i:s')
        ];

        if (file_put_contents('bus_data.json', json_encode($existingBusData, JSON_PRETTY_PRINT))) {
            // Send notification to Discord
            $webhookUrl = 'https://discord.com/api/webhooks/1321256530078597140/kS17yMHKFEIkVPvBfZWP5z9P4ADdcTSNes_XOyFGY_ieGCgo7lJBrLRukc98q3jmUIuP';
            $message = [
                'content' => null,
                'embeds' => [
                    [
                        'description' => " - Přidal: $username\n - URL: $url\n - Odkaz obrázku: $image",
                        'color' => null,
                        'author' => [
                            'name' => "Nové vozidlo #$vhcId"
                        ],
                        'image' => [
                            'url' => $image
                        ],
                        'thumbnail' => [
                            'url' => $image
                        ]
                    ]
                ],
                'attachments' => []
            ];
            
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);

            // Get updated grid HTML
            ob_start();
            ksort($existingBusData, SORT_NUMERIC);
            $busData = $existingBusData;
            include 'bus_grid.php';
            $gridHtml = ob_get_clean();

            $response['success'] = true;
            $response['message'] = 'Data byla úspěšně uložena';
            $response['gridHtml'] = $gridHtml;
        } else {
            $response['message'] = 'Chyba při ukládání dat';
        }
    } else {
        $response['message'] = 'Chybí ID vozidla';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Handle AJAX refresh request
if (isset($_GET['refresh'])) {
    $busData = [];
    if (file_exists('bus_data.json')) {
        $busData = json_decode(file_get_contents('bus_data.json'), true) ?: [];
        if (!is_array($busData)) {
            $busData = [];
            error_log('Invalid bus_data.json content - resetting to empty array');
        }
    }
    
    // Sort by vehicle ID for consistent display
    ksort($busData, SORT_NUMERIC);
    
    ob_start();
    include 'bus_grid.php';
    $gridHtml = ob_get_clean();
    echo $gridHtml;
    exit;
}

// Function to fetch and save stops data
function fetchAndSaveStops() {
    $apiBaseUrl = 'https://dukapi.sap1k.cz';
    $stopsData = file_get_contents($apiBaseUrl . '/GetStops');
    if ($stopsData !== false) {
        file_put_contents('stops.json', $stopsData);
        return true;
    }
    return false;
}

// Fetch and save stops data after successful login
if (!file_exists('stops.json') || (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true)) {
    $stopsSaved = fetchAndSaveStops();
    $_SESSION['just_logged_in'] = false;
}

// Load existing bus data with error handling
$busData = [];
if (file_exists('bus_data.json')) {
    $jsonContent = file_get_contents('bus_data.json');
    if ($jsonContent !== false) {
        $busData = json_decode($jsonContent, true) ?: [];
        if (!is_array($busData)) {
            error_log('Invalid bus_data.json content - resetting to empty array');
            $busData = [];
        }
    } else {
        error_log('Failed to read bus_data.json');
    }
}

// Sort by vehicle ID for consistent display
ksort($busData, SORT_NUMERIC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vhcId = filter_input(INPUT_POST, 'vhc_id', FILTER_SANITIZE_NUMBER_INT);
    $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);
    $url = filter_input(INPUT_POST, 'url', FILTER_SANITIZE_URL);

    if ($vhcId) {
        // Load existing bus data
        $existingBusData = [];
        if (file_exists('bus_data.json')) {
            $existingBusData = json_decode(file_get_contents('bus_data.json'), true) ?: [];
        }

        // Add or update the entry
        $existingBusData[$vhcId] = [
            'image' => $image,
            'url' => $url,
            'added_by' => $_SESSION['username'],
            'discord_id' => $_SESSION['discord_id'] ?? null,
            'added_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents('bus_data.json', json_encode($existingBusData, JSON_PRETTY_PRINT));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Správa Autobusů</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f3f4f6;
            --bg-secondary: #ffffff;
            --text-primary: #000000;
            --text-secondary: #4b5563;
        }
        
        [data-theme="dark"] {
            --bg-primary: #1f2937;
            --bg-secondary: #111827;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-primary: #1f2937;
                --bg-secondary: #111827;
                --text-primary: #ffffff;
                --text-secondary: #9ca3af;
            }
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
        }

        .card {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }

        input {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            border-color: var(--text-secondary);
        }

        .notification {
            position: fixed;
            top: -100px;
            right: 20px;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            z-index: 50;
            transition: top 0.3s ease-in-out;
            min-width: 300px;
        }

        .notification.visible {
            top: 20px;
        }

        .success {
            border-left: 4px solid #10B981;
        }

        .error {
            border-left: 4px solid #EF4444;
        }

        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .loading::after {
            content: '...';
            display: inline-block;
            animation: dots 1.5s steps(4, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <div id="notification" class="notification"></div>
    <div class="container mx-auto p-4">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-3xl font-bold">Správa Autobusů</h1>
            <div class="flex gap-4">
                <button onclick="toggleTheme()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Přepnout Téma
                </button>
                <a href="bus_data.json" download class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                    Stáhnout JSON
                </a>
            </div>
        </div>
        <p class="mb-4">Vítejte, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</p>
        <?php if (isset($stopsSaved)): ?>
            <p class="mb-4"><?php echo $stopsSaved ? "Data zastávek byla úspěšně aktualizována." : "Nepodařilo se aktualizovat data zastávek."; ?></p>
        <?php endif; ?>
        <a href="logout.php" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded mb-4 inline-block">Odhlásit se</a>
        
        <form id="busForm" class="mb-8 card p-4 rounded shadow">
            <div class="mb-4">
                <label for="vhc_id" class="block mb-2">ID Vozidla:</label>
                <input type="number" id="vhc_id" name="vhc_id" required class="w-full p-2 border rounded">
            </div>
            <div class="mb-4">
                <label for="image" class="block mb-2">URL Obrázku:</label>
                <input type="url" id="image" name="image" class="w-full p-2 border rounded">
            </div>
            <div class="mb-4">
                <label for="url" class="block mb-2">URL Seznam-autobusu.cz:</label>
                <input type="url" id="url" name="url" class="w-full p-2 border rounded">
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                Přidat/Upravit Data Autobusu
            </button>
        </form>

        <div class="mb-4">
            <h2 class="text-2xl font-bold mb-2">Filtrovat podle rozsahu</h2>
            <div class="flex flex-wrap gap-2">
                <button onclick="filterBuses('all')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Všechny
                </button>
                <button onclick="filterBuses('100-299')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    100-299
                </button>
                <button onclick="filterBuses('300-599')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    300-599
                </button>
                <button onclick="filterBuses('600-699')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    600-699
                </button>
                <button onclick="filterBuses('700-799')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    700-799
                </button>
                <button onclick="filterBuses('800-999')" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    800-999
                </button>
            </div>
        </div>
        
        <?php if (!empty($users['pending'])): ?>
        <div class="mb-8 card p-4 rounded shadow">
            <h2 class="text-2xl font-bold mb-4">Čekající Registrace</h2>
            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($users['pending'] as $userId => $user): ?>
                    <div class="border p-4 rounded" data-user-id="<?= htmlspecialchars($userId) ?>">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-bold"><?= htmlspecialchars($user['username']) ?></p>
                                <p class="text-sm text-gray-600">Registrováno: <?= htmlspecialchars($user['registered_at']) ?></p>
                            </div>
                            <div class="flex gap-2">
                                <button onclick="handleUserAction('approve', '<?= htmlspecialchars($userId) ?>')" 
                                        class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                                    Schválit
                                </button>
                                <button onclick="handleUserAction('reject', '<?= htmlspecialchars($userId) ?>')"
                                        class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                    Zamítnout
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <h2 class="text-2xl font-bold mb-4">Existující Data Autobusů</h2>
        <div id="busGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php include 'bus_grid.php'; ?>
        </div>
    </div>
    <script>
        // User approval/rejection handling
        async function handleUserAction(action, userId) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=${action}&user_id=${userId}`
                });
                
                const result = await response.json();
                showNotification(result.message, result.success ? 'success' : 'error');
                
                if (result.success) {
                    // Remove the user card from the pending list
                    const userCard = document.querySelector(`[data-user-id="${userId}"]`).closest('.border');
                    userCard.remove();
                }
            } catch (error) {
                showNotification('Došlo k chybě při komunikaci se serverem', 'error');
            }
        }
        // Form submission handling
        document.getElementById('busForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.textContent;
            
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            submitBtn.textContent = 'Ukládám';
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    showNotification(result.message, 'success');
                    if (result.gridHtml) {
                        document.getElementById('busGrid').innerHTML = result.gridHtml;
                    } else {
                        await refreshBusGrid();
                    }
                    form.reset();
                    
                    // Scroll to the newly added entry
                    const newEntry = document.querySelector(`[data-bus-id="${formData.get('vhc_id')}"]`);
                    if (newEntry) {
                        newEntry.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        newEntry.classList.add('highlight');
                        setTimeout(() => newEntry.classList.remove('highlight'), 2000);
                    }
                } else {
                    showNotification(result.message || 'Došlo k chybě při ukládání dat', 'error');
                }
            } catch (error) {
                showNotification('Došlo k chybě při komunikaci se serverem', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
                submitBtn.textContent = originalBtnText;
            }
        });

        // Notification handling
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.classList.add('visible');
            
            setTimeout(() => {
                notification.classList.remove('visible');
            }, 5000);
        }

        // Refresh bus grid
        async function refreshBusGrid() {
            try {
                const response = await fetch('?refresh=true');
                const text = await response.text();
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = text;
                
                const newGrid = tempDiv.querySelector('#busGrid');
                const currentGrid = document.getElementById('busGrid');
                
                if (newGrid && currentGrid) {
                    currentGrid.innerHTML = newGrid.innerHTML;
                }
            } catch (error) {
                console.error('Chyba při obnovování dat:', error);
            }
        }
        // Theme handling
        function toggleTheme() {
            const body = document.documentElement;
            const currentTheme = body.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        // Initialize theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }

        // Bus filtering
        function filterBuses(range) {
            const busCards = document.querySelectorAll('#busGrid .card');
            busCards.forEach(card => {
                const busId = parseInt(card.getAttribute('data-bus-id'));
                let show = true;

                if (range !== 'all') {
                    const [min, max] = range.split('-').map(Number);
                    show = busId >= min && busId <= max;
                }

                card.style.display = show ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>