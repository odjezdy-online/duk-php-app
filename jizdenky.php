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
    <title>DÚK Tarify</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            font-weight: 300;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 40px;
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3rem;
            font-weight: 400;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        select, input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            background: white;
            transition: border-color 0.2s;
        }
        
        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .autocomplete-container {
            position: relative;
        }
        
        .autocomplete-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            background: white;
            transition: border-color 0.2s;
        }
        
        .autocomplete-input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .autocomplete-item {
            padding: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .autocomplete-item:hover, .autocomplete-item.selected {
            background: #f8f9fa;
        }
        
        .autocomplete-type {
            color: #666;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
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
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .results {
            background: #f8f9fa;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .offer {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 15px;
        }
        
        .offer:last-child {
            margin-bottom: 0;
        }
        
        .price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
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
            color: #555;
        }
        
        .products {
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .product {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
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
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #666;
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
<body>
    <div class="container">
        <div class="header">
            <h1>DÚK Vyhledávač Tarifů</h1>
            <p>Doprava Ústeckého kraje</p>
        </div>
        
        <div class="content">
            <!-- Tariff Status -->
            <div class="section">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>Chyba při načítání tarifů:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <strong>Tarify načteny úspěšně</strong><br>
                        Platnost od: <?= date('j.n.Y', strtotime($tariffs['validFrom'])) ?>
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
            <div class="section">
                <h2>Vyhledání cen</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="from_zone">Z:</label>
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
                            <label for="to_zone">Do:</label>
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
                    
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="network" id="network" <?= isset($_POST['network']) ? 'checked' : '' ?>>
                            <label for="network">Síťové jízdné</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="bags" id="bags" <?= isset($_POST['bags']) ? 'checked' : '' ?>>
                            <label for="bags">Zavazadla</label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Vyhledat ceny</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Price Results -->
            <?php if ($priceError): ?>
                <div class="section">
                    <div class="alert alert-error">
                        <strong>Chyba při vyhledávání:</strong> <?= htmlspecialchars($priceError) ?>
                    </div>
                </div>
            <?php elseif ($priceResults): ?>
                <div class="section">
                    <h2>Výsledky vyhledávání</h2>
                    
                    <?php if (isset($priceResults['zoneDistance'])): ?>
                        <p><strong>Vzdálenost zón:</strong> <?= $priceResults['zoneDistance'] ?></p>
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
                                <strong>Produkty:</strong>
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
                                        ✓ Přestupní
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
                                Pro zadané parametry nebyly nalezeny žádné nabídky.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
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