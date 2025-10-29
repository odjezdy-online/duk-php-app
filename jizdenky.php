<?php
// DÚK API Configuration
const API_BASE = 'https://client.dukapka.cz/api/v1';
const API_KEY = '980z80c35x';

// Helper function to make API requests
function makeApiRequest($url, $postData = null) {
    $headers = [
        'X-API-Key: ' . API_KEY,
        'User-Agent: DUK-PHP-Prototype/1.0'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: $httpCode");
    }
    
    return json_decode($response, true);
}

// Format price from halers to crowns
function formatPrice($priceInHalers) {
    return number_format($priceInHalers / 100, 2, ',', ' ') . ' Kč';
}

// Load tariff data
$tariffs = null;
$zones = [];
$stops = [];
$cps = [];
$tps = [];
$error = null;

try {
    $url = API_BASE . '/price-lists/current?cps=1&tps=1&zones=1&superzones=0&stops=1&tariffs=1';
    $tariffs = makeApiRequest($url);
    $zones = $tariffs['zones'] ?? [];
    $stops = $tariffs['stops'] ?? [];
    $cps = $tariffs['cps'] ?? [];
    $tps = $tariffs['tps'] ?? [];
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Handle autocomplete search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = strtolower($_GET['search']);
    $results = [];
    
    // Search in zones
    foreach ($zones as $zone) {
        if (strpos(strtolower($zone['name']), $searchTerm) !== false) {
            $results[] = [
                'name' => $zone['name'],
                'number' => $zone['number'],
                'type' => 'zóna'
            ];
        }
    }
    
    // Search in stops
    foreach ($stops as $stop) {
        if (strpos(strtolower($stop['name']), $searchTerm) !== false) {
            $results[] = [
                'name' => $stop['name'],
                'number' => $stop['zone'],
                'type' => 'zastávka'
            ];
        }
    }
    
    // Limit results
    $results = array_slice($results, 0, 10);
    
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// Handle price search
$priceResults = null;
$priceError = null;

if ($_POST && isset($_POST['from_zone']) && isset($_POST['to_zone'])) {
    try {
        $requestData = [
            'from' => (int)$_POST['from_zone'],
            'to' => (int)$_POST['to_zone'],
            'network' => isset($_POST['network']),
            'bags' => isset($_POST['bags']),
            'cps' => null,
            'tps' => null,
            'dukapkaRegLevel' => null
        ];
        
        $url = API_BASE . '/pricing/by-zones';
        $priceResults = makeApiRequest($url, $requestData);
    } catch (Exception $e) {
        $priceError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DÚK Tarify - Dopravní Údaje Kolektiv</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    tailwind.config = {
        darkMode: 'media',
        theme: {
            extend: {
                colors: {
                    primary: {
                        50: '#eff6ff',
                        100: '#dbeafe',
                        200: '#bfdbfe',
                        300: '#93c5fd',
                        400: '#60a5fa',
                        500: '#3b82f6',
                        600: '#2563eb',
                        700: '#1d4ed8',
                        800: '#1e40af',
                        900: '#1e3a8a',
                    }
                }
            }
        }
    }
    </script>
    <style>
    :root {
        --text: #0f172a;
        --text-light: #64748b;
        --bg-primary: #ffffff;
        --bg-secondary: #f8fafc;
        --accent: #2563eb;
        --border: #e2e8f0;
        --success: #10b981;
        --warning: #f59e0b;
        --error: #ef4444;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --text: #f8fafc;
            --text-light: #94a3b8;
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --accent: #3b82f6;
            --border: #334155;
            --success: #34d399;
            --warning: #fbbf24;
            --error: #f87171;
        }
    }

    body {
        background-color: var(--bg-secondary);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .autocomplete-container {
        position: relative;
    }

    .autocomplete-input {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        background-color: var(--bg-primary);
        color: var(--text);
        font-size: 1rem;
        transition: all 0.2s;
    }

    .autocomplete-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }

    .autocomplete-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-top: none;
        border-radius: 0 0 0.5rem 0.5rem;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
    }

    .autocomplete-item {
        padding: 12px;
        cursor: pointer;
        border-bottom: 1px solid var(--border);
        font-size: 0.9rem;
        transition: background-color 0.2s;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item:hover, .autocomplete-item.selected {
        background: var(--bg-secondary);
    }

    .autocomplete-type {
        color: var(--text-light);
        font-size: 0.8rem;
        margin-left: 8px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .checkbox-item input[type="checkbox"] {
        width: auto;
    }

    .btn {
        background: var(--accent);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 1rem;
        transition: background-color 0.2s;
        font-weight: 500;
    }

    .btn:hover {
        background: var(--accent);
        filter: brightness(1.1);
    }

    .alert {
        padding: 15px;
        border-radius: 0.5rem;
        margin-bottom: 20px;
    }

    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--error);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    .alert-info {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .results {
        background: var(--bg-secondary);
        border-radius: 0.5rem;
        padding: 20px;
        margin-top: 20px;
    }

    .offer {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        padding: 20px;
        margin-bottom: 15px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .offer:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    .offer:last-child {
        margin-bottom: 0;
    }

    .price {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--accent);
        margin-bottom: 10px;
    }

    .offer-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-bottom: 15px;
        font-size: 0.9rem;
    }

    .offer-details strong {
        color: var(--text-light);
    }

    .products {
        border-top: 1px solid var(--border);
        padding-top: 15px;
        margin-top: 15px;
    }

    .product {
        background: var(--bg-secondary);
        padding: 10px;
        border-radius: 0.25rem;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat {
        text-align: center;
        padding: 15px;
        background: var(--bg-primary);
        border-radius: 0.5rem;
        border: 1px solid var(--border);
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--accent);
    }

    .stat-label {
        font-size: 0.8rem;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    @media (max-width: 600px) {
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .checkbox-group {
            flex-direction: column;
            gap: 10px;
        }
        
        .offer-details {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <header class="py-6 mb-8 bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <div class="container mx-auto px-4">
            <h1 class="text-3xl md:text-4xl font-bold text-center">Dopravní Údaje Kolektiv</h1>
            <p class="text-blue-200 text-center mt-2">Vyhledávač tarifů DÚK</p>
            <div class="mt-4 flex justify-center space-x-4">
                <a href="index.php" class="bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg font-semibold transition-all duration-200">
                    <i class="fas fa-home mr-2"></i>
                    Hlavní stránka
                </a>
                <a href="login.php" class="bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg font-semibold transition-all duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Přihlášení
                </a>
            </div>
        </div>
    </header>
        
</style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <header class="py-6 mb-8 bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
        <div class="container mx-auto px-4">
            <h1 class="text-3xl md:text-4xl font-bold text-center">Dopravní Údaje Kolektiv</h1>
            <p class="text-blue-200 text-center mt-2">Vyhledávač tarifů DÚK</p>
            <div class="mt-4 flex justify-center space-x-4">
                <a href="index.php" class="bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg font-semibold transition-all duration-200">
                    <i class="fas fa-home mr-2"></i>
                    Hlavní stránka
                </a>
                <a href="login.php" class="bg-white text-blue-600 hover:bg-blue-50 px-4 py-2 rounded-lg font-semibold transition-all duration-200">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Přihlášení
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 mb-12 flex-grow">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl overflow-hidden max-w-4xl mx-auto">
            <div class="p-8">
                <!-- Tariff Status -->
                <div class="mb-8">
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Chyba při načítání tarifů:</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <div class="flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                <div>
                                    <strong>Tarify načteny úspěšně</strong><br>
                                    Platnost od: <?= date('j.n.Y', strtotime($tariffs['validFrom'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats">
                            <div class="stat">
                                <div class="stat-number"><?= count($zones) ?></div>
                                <div class="stat-label">Zóny</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number"><?= count($cps) ?></div>
                                <div class="stat-label">Cenové profily</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number"><?= count($stops) ?></div>
                                <div class="stat-label">Zastávky</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Price Search Form -->
                <?php if (!$error): ?>
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Vyhledání cen</h2>
                    <form method="post" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="from_zone" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Z:</label>
                                <div class="autocomplete-container">
                                    <input type="text" 
                                           class="autocomplete-input" 
                                           id="from_input" 
                                           placeholder="Začněte psát název zóny nebo zastávky..."
                                           value="<?= htmlspecialchars($_POST['from_display'] ?? '') ?>"
                                           autocomplete="off">
                                    <input type="hidden" name="from_zone" id="from_zone" value="<?= htmlspecialchars($_POST['from_zone'] ?? '') ?>">
                                    <input type="hidden" name="from_display" id="from_display" value="<?= htmlspecialchars($_POST['from_display'] ?? '') ?>">
                                    <div class="autocomplete-results" id="from_results"></div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="to_zone" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Do:</label>
                                <div class="autocomplete-container">
                                    <input type="text" 
                                           class="autocomplete-input" 
                                           id="to_input" 
                                           placeholder="Začněte psát název zóny nebo zastávky..."
                                           value="<?= htmlspecialchars($_POST['to_display'] ?? '') ?>"
                                           autocomplete="off">
                                    <input type="hidden" name="to_zone" id="to_zone" value="<?= htmlspecialchars($_POST['to_zone'] ?? '') ?>">
                                    <input type="hidden" name="to_display" id="to_display" value="<?= htmlspecialchars($_POST['to_display'] ?? '') ?>">
                                    <div class="autocomplete-results" id="to_results"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox-group flex flex-col md:flex-row gap-4">
                            <div class="checkbox-item">
                                <input type="checkbox" name="network" id="network" <?= isset($_POST['network']) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="network" class="text-sm font-medium text-gray-700 dark:text-gray-300">Síťové jízdné</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" name="bags" id="bags" <?= isset($_POST['bags']) ? 'checked' : '' ?> class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                <label for="bags" class="text-sm font-medium text-gray-700 dark:text-gray-300">Zavazadla</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105">
                            <i class="fas fa-search mr-2"></i>
                            Vyhledat ceny
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Price Results -->
                <?php if ($priceError): ?>
                    <div class="mb-8">
                        <div class="alert alert-error">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <strong>Chyba při vyhledávání:</strong> <?= htmlspecialchars($priceError) ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($priceResults): ?>
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6">Výsledky vyhledávání</h2>
                        
                        <?php if (isset($priceResults['zoneDistance'])): ?>
                            <p class="text-gray-700 dark:text-gray-300 mb-4"><strong>Vzdálenost zón:</strong> <?= $priceResults['zoneDistance'] ?></p>
                        <?php endif; ?>
                        
                        <div class="results">
                            <?php 
                            $hasOffers = false;
                            if (isset($priceResults['coverages'])):
                                foreach ($priceResults['coverages'] as $coverage):
                                    if (isset($coverage['offers'])):
                                        foreach ($coverage['offers'] as $offer):
                                            $hasOffers = true;
                                            
                                            // Find CP and TP names
                                            $cpName = 'N/A';
                                            $tpName = 'N/A';
                                            
                                            foreach ($cps as $cp) {
                                                if ($cp['number'] == $offer['cp']) {
                                                    $cpName = $cp['name'];
                                                    break;
                                                }
                                            }
                                            
                                            foreach ($tps as $tp) {
                                                if ($tp['number'] == $offer['tp']) {
                                                    $tpName = $tp['name'];
                                                    break;
                                                }
                                            }
                            ?>
                            <div class="offer">
                                <div class="price"><?= formatPrice($offer['price']) ?></div>
                                
                                <div class="offer-details">
                                    <div><strong>Profil:</strong> <?= htmlspecialchars($cpName) ?></div>
                                    <div><strong>Typ:</strong> <?= htmlspecialchars($tpName) ?></div>
                                    <div><strong>Formát:</strong> <?= htmlspecialchars($offer['format'] ?? 'N/A') ?></div>
                                    <div><strong>Platba:</strong> <?= htmlspecialchars($offer['payment'] ?? 'N/A') ?></div>
                                </div>
                                
                                <?php if (isset($offer['products']) && !empty($offer['products'])): ?>
                                <div class="products">
                                    <strong class="text-gray-700 dark:text-gray-300">Produkty:</strong>
                                    <?php foreach ($offer['products'] as $product): ?>
                                    <div class="product">
                                        <?= htmlspecialchars($product['name']) ?>
                                        <?php if (isset($product['validDurationMinutes'])): ?>
                                            (<?= $product['validDurationMinutes'] ?> min)
                                        <?php endif; ?>
                                        <?php if (isset($product['validDurationDays'])): ?>
                                            (<?= $product['validDurationDays'] ?> dní)
                                        <?php endif; ?>
                                        <?php if (isset($product['transfer']) && $product['transfer']): ?>
                                            <span class="text-green-600 dark:text-green-400 ml-2">✓ Přestupní</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php 
                                        endforeach;
                                    endif;
                                endforeach;
                            endif;
                            
                            if (!$hasOffers): ?>
                                <div class="alert alert-info">
                                    <div class="flex items-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Pro zadané parametry nebyly nalezeny žádné nabídky.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="py-4 bg-gray-800 text-white text-center text-sm">
        <div class="container mx-auto">
            <p>© 2023 - Dopravní Údaje Kolektiv</p>
        </div>
    </footer>
    
    <script>
        class AutoComplete {
            constructor(inputId, resultsId, hiddenId, hiddenDisplayId) {
                this.input = document.getElementById(inputId);
                this.results = document.getElementById(resultsId);
                this.hidden = document.getElementById(hiddenId);
                this.hiddenDisplay = document.getElementById(hiddenDisplayId);
                this.selectedIndex = -1;
                this.currentResults = [];
                
                this.init();
            }
            
            init() {
                this.input.addEventListener('input', this.onInput.bind(this));
                this.input.addEventListener('keydown', this.onKeyDown.bind(this));
                this.input.addEventListener('blur', this.onBlur.bind(this));
                this.input.addEventListener('focus', this.onFocus.bind(this));
                
                document.addEventListener('click', (e) => {
                    if (!this.input.contains(e.target) && !this.results.contains(e.target)) {
                        this.hideResults();
                    }
                });
            }
            
            async onInput() {
                const query = this.input.value.trim();
                
                if (query.length < 2) {
                    this.hideResults();
                    this.hidden.value = '';
                    return;
                }
                
                try {
                    const response = await fetch(`?search=${encodeURIComponent(query)}`);
                    const results = await response.json();
                    this.showResults(results);
                } catch (error) {
                    console.error('Search error:', error);
                    this.hideResults();
                }
            }
            
            onKeyDown(e) {
                if (!this.results.style.display || this.results.style.display === 'none') {
                    return;
                }
                
                switch (e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        this.selectedIndex = Math.min(this.selectedIndex + 1, this.currentResults.length - 1);
                        this.updateSelection();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                        this.updateSelection();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (this.selectedIndex >= 0) {
                            this.selectItem(this.currentResults[this.selectedIndex]);
                        }
                        break;
                    case 'Escape':
                        this.hideResults();
                        break;
                }
            }
            
            onBlur() {
                // Delay hiding to allow clicks on results
                setTimeout(() => {
                    this.hideResults();
                }, 200);
            }
            
            onFocus() {
                if (this.input.value.length >= 2) {
                    this.onInput();
                }
            }
            
            showResults(results) {
                this.currentResults = results;
                this.selectedIndex = -1;
                
                if (results.length === 0) {
                    this.hideResults();
                    return;
                }
                
                this.results.innerHTML = '';
                
                results.forEach((item, index) => {
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = `
                        ${item.name}
                        <span class="autocomplete-type">${item.type}</span>
                    `;
                    
                    div.addEventListener('click', () => {
                        this.selectItem(item);
                    });
                    
                    this.results.appendChild(div);
                });
                
                this.results.style.display = 'block';
            }
            
            hideResults() {
                this.results.style.display = 'none';
                this.selectedIndex = -1;
            }
            
            updateSelection() {
                const items = this.results.querySelectorAll('.autocomplete-item');
                items.forEach((item, index) => {
                    item.classList.toggle('selected', index === this.selectedIndex);
                });
            }
            
            selectItem(item) {
                this.input.value = `${item.name} (${item.type})`;
                this.hidden.value = item.number;
                this.hiddenDisplay.value = `${item.name} (${item.type})`;
                this.hideResults();
            }
        }
        
        // Initialize autocomplete for both inputs
        document.addEventListener('DOMContentLoaded', function() {
            new AutoComplete('from_input', 'from_results', 'from_zone', 'from_display');
            new AutoComplete('to_input', 'to_results', 'to_zone', 'to_display');
        });
    </script>
</body>
</html>
