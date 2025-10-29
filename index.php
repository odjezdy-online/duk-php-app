<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mapyCzApiKey = 'U947ociL6rDY4KSP_x2jcNKJ-o5b2pPxVxW_t8Q8RGU';
// $apiBaseUrl = 'https://bilina.odjezdy.online/api';
$apiBaseUrl = 'https://dukfinder.sap1k.cz/api';

function callApi($endpoint, $method = 'POST', $data = null) {
    global $apiBaseUrl;
    $url = $apiBaseUrl . $endpoint;
    $options = [
        'http' => [
            'method' => $method,
            'header' => 'Content-Type: application/json',
            'content' => $data !== null ? json_encode($data) : '',
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    error_log("API Call to $url: " . ($response === false ? "Failed" : "Success"));
    error_log("Raw Response: " . $response);
    
    return json_decode($response, true);
}
$apiBaseUrl_SAP1K = 'https://dukfinder.sap1k.cz/api';

// Funkce pro volání API
function callApiSap($endpoint, $method = 'POST', $data = null) {
    global $apiBaseUrl_SAP1K;
    $url = $apiBaseUrl_SAP1K . $endpoint;
    $options = [
        'http' => [
            'method' => $method,
            'header' => 'Content-Type: application/json',
            'content' => $data !== null ? json_encode($data) : '',
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    return json_decode($response, true);
}

$stopsFile = 'stops.json';
if (file_exists($stopsFile)) {
    $stops = json_decode(file_get_contents($stopsFile), true);
} else {
    $stops = [];
    $error = 'Data zastávek nejsou k dispozici. Přihlaste se prosím do administračního panelu pro aktualizaci zastávek.';
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false];

    switch ($_GET['action']) {
        case 'getDepartures':
            $stopId = filter_input(INPUT_GET, 'stop_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $departures = callApiSap('/GetRTDepartures', 'POST', ['stop_id' => $stopId]);
            $response = ['success' => is_array($departures), 'data' => $departures];
            break;
        case 'getVehicleInfo':
            $trip = filter_input(INPUT_GET, 'trip', FILTER_VALIDATE_INT);
            $line = filter_input(INPUT_GET, 'line', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            
            $vehicleInfo = callApi('/GetVhcInfoByTrip', 'POST', ['trip' => $trip, 'line_displayed' => $line]);
            
            if (isset($vehicleInfo['vhc_id'])) {
                $vehicleDetails = callApiSap('/GetVhcDetailsByID', 'POST', ['ID' => $vehicleInfo['vhc_id']]);
                $vehiclePos = callApiSap('/GetVhcPos', 'POST', [
                    'vhc_id' => $vehicleInfo['vhc_id'],
                    'line_displayed' => $line,
                    'trip' => $trip
                ]);
                $stopsOnTrip = callApiSap('/GetStopsOnTrip', 'POST', ['trip' => $trip, 'line_displayed' => $line]);
                $tripGeometry = callApiSap('/GetTripGeometry', 'POST', ['trip' => $trip, 'line_displayed' => $line]);
                
                $response = [
                    'success' => true,
                    'data' => [
                        'info' => $vehicleInfo,
                        'details' => $vehicleDetails,
                        'position' => $vehiclePos[0] ?? null,
                        'stopsOnTrip' => $stopsOnTrip,
                        'tripGeometry' => $tripGeometry
                    ]
                ];
            } else {
                $response['error'] = 'Nepodařilo se získat informace o vozidle';
            }
            break;
    }

    echo json_encode($response);
    exit;
}

$busData = [];
if (file_exists('bus_data.json')) {
    $busData = json_decode(file_get_contents('bus_data.json'), true);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informace o veřejné dopravě v Ústeckém kraji</title>
    <meta name="description" content="Aktuální informace o odjezdech a zpoždění veřejné dopravy v Ústeckém kraji. Sledujte polohu autobusů a vlaků v reálném čase.">
    <meta name="keywords" content="veřejná doprava, Ústecký kraj, autobusy, vlaky, odjezdy, zpoždění, jízdní řády">
    <meta property="og:title" content="Informace o veřejné dopravě v Ústeckém kraji">
    <meta property="og:description" content="Aktuální informace o odjezdech a zpoždění veřejné dopravy v Ústeckém kraji. Sledujte polohu autobusů a vlaků v reálném čase.">
    <meta property="og:url" content="https://amz.odjezdy.online/duk">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="https://amz.odjezdy.online/duk">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.css" />
    <link href="css/leaflet.icon-material.css" rel="stylesheet">
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
                    },
                    secondary: {
                        50: '#f8fafc',
                        100: '#f1f5f9',
                        200: '#e2e8f0',
                        300: '#cbd5e1',
                        400: '#94a3b8',
                        500: '#64748b',
                        600: '#475569',
                        700: '#334155',
                        800: '#1e293b',
                        900: '#0f172a',
                    }
                },
                fontFamily: {
                    'outfit': ['Outfit', 'sans-serif'],
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
        --early: #8b5cf6;
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
            --early: #a78bfa;
        }
    }

    body {
        font-family: 'Outfit', sans-serif;
        background-color: var(--bg-secondary);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* Popup styling */
    .modal-container {
        display: none;
        position: fixed;
        inset: 0;
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 50;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease-out forwards;
    }

    .modal {
        background-color: var(--bg-primary);
        border-radius: 1rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        width: 95%;
        max-width: 900px;
        max-height: 90vh;
        overflow-y: auto;
        padding: 2rem;
        animation: slideUp 0.3s ease-out forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { 
            opacity: 0;
            transform: translateY(20px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }

    .close-modal {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background-color: var(--border);
        color: var(--text);
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 9999px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .close-modal:hover {
        background-color: var(--error);
        color: white;
        transform: rotate(90deg);
    }

    /* Custom select styling */
    .stop-select-container {
        position: relative;
    }

    .stop-select-container i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
        pointer-events: none;
    }

    select.stop-select,
    input.stop-search {
        width: 100%;
        padding: 1rem 1rem 1rem 3rem;
        border: 2px solid var(--border);
        border-radius: 0.75rem;
        background-color: var(--bg-primary);
        color: var(--text);
        font-size: 1rem;
        appearance: none;
        outline: none;
        transition: all 0.2s;
    }

    input.stop-search:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    }

    /* Button styling */
    .btn-primary {
        background-color: var(--accent);
        color: white;
        font-weight: 600;
        padding: 1rem 2rem;
        border-radius: 0.75rem;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        width: 100%;
    }

    .btn-primary:hover {
        filter: brightness(1.1);
        transform: translateY(-2px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .btn-secondary {
        background-color: transparent;
        color: var(--accent);
        font-weight: 500;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        transition: all 0.2s;
        border: 1px solid var(--accent);
    }

    .btn-secondary:hover {
        background-color: var(--accent);
        color: white;
    }

    /* Card styling */
    .card {
        background-color: var(--bg-primary);
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: all 0.3s;
    }

    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    /* Map container */
    #map-container {
        height: 400px;
        border-radius: 0.75rem;
        overflow: hidden;
        margin-top: 1.5rem;
        display: none;
    }

    /* Departure styling */
    .departure-item {
        padding: 1.5rem;
        margin-bottom: 1rem;
    }

    .line-badge {
        background-color: var(--accent);
        color: white;
        font-weight: 700;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        display: inline-block;
    }

    .delay-text {
        font-weight: 600;
        transition: color 0.3s;
    }

    /* Feature badges */
    .feature-badge {
        display: flex;
        align-items: center;
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
        margin-right: 0.5rem;
    }

    .feature-badge i {
        margin-right: 0.25rem;
        font-size: 1rem;
    }

    .feature-badge.positive {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }

    .feature-badge.negative {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--error);
    }

    /* Loading indicator */
    .loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .loading-spinner {
        border: 4px solid rgba(99, 102, 241, 0.1);
        border-radius: 50%;
        border-top: 4px solid var(--accent);
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-XSRCJY27KX"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-XSRCJY27KX');
    </script>
</head>
<body>
    <header class="py-6 mb-8 bg-primary-700 text-white">
        <div class="container mx-auto px-4">
            <h1 class="text-3xl md:text-4xl font-bold text-center">Informace o veřejné dopravě</h1>
            <p class="text-primary-200 text-center mt-2">Sledujte aktuální odjezdy a polohy vozidel v reálném čase</p>
        </div>
    </header>

    <main class="container mx-auto px-4 mb-12 flex-grow">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <span class="block"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <section class="card p-6 md:p-8 max-w-2xl mx-auto mb-8">
            <h2 class="text-xl font-bold mb-4">Vyhledat odjezdy</h2>
            
            <form id="search-form" class="space-y-4">
                <div class="stop-select-container">
                    <i class="material-icons">search</i>
                    <input 
                        type="text" 
                        class="stop-search" 
                        id="stop-search" 
                        placeholder="Začněte psát název zastávky..." 
                        list="stop-options"
                        autocomplete="off"
                    >
                    <datalist id="stop-options">
                        <?php foreach ($stops as $stop): ?>
                            <option data-value="<?= htmlspecialchars($stop['stop_id']) ?>" value="<?= htmlspecialchars($stop['stop_name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <input type="hidden" id="selected-stop-id" name="stop_id">
                <button type="submit" class="btn-primary">
                    Získat odjezdy
                </button>
            </form>
        </section>

        <section id="departures-container" class="max-w-4xl mx-auto">
            <div id="loading" class="loading hidden">
                <div class="loading-spinner"></div>
                <span class="ml-3">Načítání odjezdů...</span>
            </div>
            <div id="departures-list"></div>
        </section>
    </main>

    <footer class="py-4 bg-secondary-800 text-white text-center text-sm">
        <div class="container mx-auto">
            <p>© 2023 - Informační systém veřejné dopravy Ústeckého kraje</p>
        </div>
    </footer>

    <!-- Modal for vehicle details -->
    <div id="vehicle-modal" class="modal-container">
        <div class="modal relative">
            <div class="close-modal">
                <i class="material-icons">close</i>
            </div>
            <div id="vehicle-details"></div>
            <div id="map-container"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="js/leaflet.icon-material.js"></script>
    <script>
    // Global variables
    const API_KEY = '<?= $mapyCzApiKey ?>';
    let map, vehicleMarker, tripPath;
    let updateInterval = null;
    const searchForm = document.getElementById('search-form');
    const stopSearch = document.getElementById('stop-search');
    const selectedStopId = document.getElementById('selected-stop-id');
    const departuresList = document.getElementById('departures-list');
    const loadingIndicator = document.getElementById('loading');
    const vehicleModal = document.getElementById('vehicle-modal');
    const vehicleDetails = document.getElementById('vehicle-details');
    const mapContainer = document.getElementById('map-container');
    const closeModalBtn = document.querySelector('.close-modal');

    // Handle stop selection
    stopSearch.addEventListener('input', function() {
        const val = this.value;
        const options = document.getElementById('stop-options').getElementsByTagName('option');
        
        for (let i = 0; i < options.length; i++) {
            if (options[i].value === val) {
                selectedStopId.value = options[i].getAttribute('data-value');
                break;
            }
        }
    });

    // Handle form submission
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!selectedStopId.value) {
            alert('Prosím vyberte zastávku ze seznamu');
            return;
        }
        
        loadingIndicator.classList.remove('hidden');
        departuresList.innerHTML = '';
        
        getDepartures(selectedStopId.value);
    });

    // Close modal on click
    closeModalBtn.addEventListener('click', closeModal);
    vehicleModal.addEventListener('click', function(e) {
        if (e.target === vehicleModal) {
            closeModal();
        }
    });

    // Escape key to close modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && vehicleModal.style.display === 'flex') {
            closeModal();
        }
    });

    function closeModal() {
        vehicleModal.style.display = 'none';
        
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
        
        if (map) {
            map.remove();
            map = null;
        }
    }

    function getDepartures(stopId) {
        fetch(`index.php?action=getDepartures&stop_id=${encodeURIComponent(stopId)}`)
            .then(response => response.json())
            .then(data => {
                loadingIndicator.classList.add('hidden');
                
                if (data.success) {
                    displayDepartures(data.data);
                } else {
                    departuresList.innerHTML = '<div class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-lg">Nepodařilo se načíst odjezdy. Zkuste to prosím znovu.</div>';
                    console.error('Error loading departures:', data.error);
                }
            })
            .catch(error => {
                loadingIndicator.classList.add('hidden');
                departuresList.innerHTML = '<div class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-lg">Chyba při načítání odjezdů. Zkuste to prosím znovu.</div>';
                console.error('Error:', error);
            });
    }

    function displayDepartures(departures) {
        let html = '<h2 class="text-2xl font-bold mb-4">Odjezdy</h2>';
        
        if (!Array.isArray(departures) || departures.length === 0) {
            html += '<p class="text-secondary-500">Žádné odjezdy nebyly nalezeny.</p>';
            departuresList.innerHTML = html;
            return;
        }

        const now = new Date();
        const twoHoursLater = new Date(now.getTime() + 2 * 60 * 60 * 1000);
        let departuresFound = false;

        departures.forEach(dep => {
            const depTime = new Date(now.toDateString() + ' ' + dep.planned_departure);
            
            if (depTime >= now && depTime <= twoHoursLater) {
                departuresFound = true;
                const delayColor = getDelayColor(dep.delay);
                const delayText = getDelayText(dep.delay);
                
                html += `
                    <div class="card departure-item mb-4">
                        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                            <div>
                                <div class="flex items-center mb-2">
                                    <span class="line-badge mr-2">${dep.line}</span>
                                    <span class="text-secondary-500 text-sm">Spoj ${dep.trip}</span>
                                </div>
                                <p class="font-medium">Směr: ${dep.last_stop}</p>
                                <div class="flex flex-wrap mt-2">
                                    <span class="feature-badge ${dep.low_floor ? 'positive' : 'negative'}">
                                        <i class="material-icons">${dep.low_floor ? 'check_circle' : 'cancel'}</i>
                                        Nízkopodlažní
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-secondary-500">Plánovaný odjezd</p>
                                <p class="text-xl font-bold">${dep.planned_departure}</p>
                                <p class="delay-text" style="color: ${delayColor}">${delayText}</p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-secondary-200 dark:border-secondary-700 flex justify-end">
                            <button 
                                class="btn-secondary"
                                onclick="getVehicleInfo('${dep.trip}', '${dep.line}')"
                            >
                                <i class="material-icons align-middle mr-1" style="font-size: 18px;">info</i>
                                Více informací
                            </button>
                        </div>
                    </div>
                `;
            }
        });

        if (!departuresFound) {
            html += '<p class="text-secondary-500">Žádné odjezdy nebyly nalezeny v následujících 2 hodinách.</p>';
        }

        departuresList.innerHTML = html;
    }

    // Initial vehicle info fetching (isUpdate=false) or silent update (isUpdate=true)
    function getVehicleInfo(trip, line, isUpdate = false) {
        if (updateInterval) {
            clearInterval(updateInterval);
        }

        // Only show loading and open modal for first load
        if (!isUpdate) {
            // Show loading in modal
            vehicleDetails.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <span class="ml-3">Načítání informací o vozidle...</span>
                </div>
            `;
            
            mapContainer.style.display = 'none';
            vehicleModal.style.display = 'flex';
        }

        fetch(`index.php?action=getVehicleInfo&trip=${trip}&line=${encodeURIComponent(line)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (isUpdate) {
                        // Update only changed elements
                        updateVehicleInfo(data.data);
                    } else {
                        // Full display for initial load
                        displayVehicleInfo(data.data);
                    }
                    
                    if (isVehicleAvailable(data.data)) {
                        if (!map || !isUpdate) {
                            // Initialize map for first load
                            initializeMap(data.data);
                        } else {
                            // Update just the marker position
                            updateVehiclePosition(data.data);
                        }
                    }
                    
                    startAutoUpdate(trip, line);
                } else {
                    if (!isUpdate) {
                        vehicleDetails.innerHTML = `
                            <div class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-lg">
                                Nepodařilo se načíst informace o vozidle. Zkuste to prosím znovu.
                            </div>
                        `;
                    }
                    console.error('Error loading vehicle info:', data.error);
                }
            })
            .catch(error => {
                if (!isUpdate) {
                    vehicleDetails.innerHTML = `
                        <div class="bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 p-4 rounded-lg">
                            Chyba při načítání informací o vozidle. Zkuste to prosím znovu.
                        </div>
                    `;
                }
                console.error('Error:', error);
            });
    }

    function isVehicleAvailable(data) {
        return data.info.on_trip && 
               data.position && 
               data.position.lat && 
               data.position.lng && 
               data.info.last_ping;
    }

    function displayVehicleInfo(data) {
        const busData = <?= json_encode($busData) ?>;
        const busInfo = busData[data.info.vhc_id] || { image: '', url: '' };
        const isAvailable = isVehicleAvailable(data);
        
        let html = `
            <h2 class="text-2xl font-bold mb-6">Informace o vozidle</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-3">Aktuální provoz</h3>
                    <div class="space-y-3">
                        <p><span class="font-medium">Linka:</span> <span class="vehicle-line">${data.info.line_displayed}</span></p>
                        <p><span class="font-medium">Spoj:</span> <span class="vehicle-trip">${data.info.trip}</span></p>
                        <p><span class="font-medium">Aktuální zastávka:</span> <span class="vehicle-current-stop">${data.info.current_stop || 'N/A'}</span></p>
                        <p><span class="font-medium">Další zastávka:</span> <span class="vehicle-next-stop">${data.info.end_stop || 'N/A'}</span></p>
                        <p>
                            <span class="font-medium">Zpoždění:</span> 
                            <span class="vehicle-delay" style="color: ${getDelayColor(data.info.delay)}">${data.info.delay} minut</span>
                        </p>
                        <p><span class="font-medium">Poslední aktualizace:</span> <span class="vehicle-last-ping">${data.info.last_ping || 'N/A'}</span></p>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-3">Detaily vozidla</h3>
                    <div class="space-y-3">
                        <p><span class="font-medium">Evidenční číslo:</span> <span class="vehicle-id">${data.info.vhc_id}</span></p>
                        <p><span class="font-medium">Model:</span> <span class="vehicle-model">${data.details.model || 'N/A'}</span></p>
                        <p><span class="font-medium">Dopravce:</span> <span class="vehicle-agency">${data.details.agency || 'N/A'}</span></p>
                        <p><span class="font-medium">Rok výroby:</span> <span class="vehicle-year">${data.details.year_of_manufacture || 'N/A'}</span></p>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-4">
                            <div class="feature-badge ${data.details.accessible ? 'positive' : 'negative'}">
                                <i class="material-icons">${data.details.accessible ? 'check_circle' : 'cancel'}</i>
                                Bezbariérové
                            </div>
                            <div class="feature-badge ${data.details.contactless_payments ? 'positive' : 'negative'}">
                                <i class="material-icons">${data.details.contactless_payments ? 'check_circle' : 'cancel'}</i>
                                Platby kartou
                            </div>
                            <div class="feature-badge ${data.details.air_conditioning ? 'positive' : 'negative'}">
                                <i class="material-icons">${data.details.air_conditioning ? 'check_circle' : 'cancel'}</i>
                                Klimatizace
                            </div>
                            <div class="feature-badge ${data.details.usb_chargers ? 'positive' : 'negative'}">
                                <i class="material-icons">${data.details.usb_chargers ? 'check_circle' : 'cancel'}</i>
                                USB nabíječky
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (!isAvailable) {
            html += `
                <div class="bg-secondary-100 dark:bg-secondary-800 p-4 rounded-lg text-center my-6">
                    <i class="material-icons text-secondary-500 text-4xl mb-2">info</i>
                    <p>Vozidlo momentálně není na lince nebo nemá dostupná data o poloze.</p>
                </div>
            `;
        }

        if (busInfo.image) {
            html += `
                <div class="mt-6">
                    <img src="${busInfo.image}" alt="Obrázek autobusu" class="rounded-lg max-w-full h-auto">
                </div>
            `;
        }

        if (busInfo.url) {
            html += `
                <div class="mt-4">
                    <a href="${busInfo.url}" target="_blank" class="text-primary-600 hover:text-primary-800 flex items-center">
                        <i class="material-icons mr-1" style="font-size: 18px;">launch</i>
                        Zobrazit na Seznam-autobusu.cz
                    </a>
                </div>
            `;
        }

        vehicleDetails.innerHTML = html;
    }

    // Update only changed parts of vehicle info without redrawing everything
    function updateVehicleInfo(data) {
        // Update current operation info
        document.querySelector('.vehicle-line').textContent = data.info.line_displayed;
        document.querySelector('.vehicle-trip').textContent = data.info.trip;
        document.querySelector('.vehicle-current-stop').textContent = data.info.current_stop || 'N/A';
        document.querySelector('.vehicle-next-stop').textContent = data.info.end_stop || 'N/A';
        
        const delayElement = document.querySelector('.vehicle-delay');
        delayElement.textContent = `${data.info.delay} minut`;
        delayElement.style.color = getDelayColor(data.info.delay);
        
        document.querySelector('.vehicle-last-ping').textContent = data.info.last_ping || 'N/A';
        
        // Update vehicle details
        document.querySelector('.vehicle-id').textContent = data.info.vhc_id;
        document.querySelector('.vehicle-model').textContent = data.details.model || 'N/A';
        document.querySelector('.vehicle-agency').textContent = data.details.agency || 'N/A';
        document.querySelector('.vehicle-year').textContent = data.details.year_of_manufacture || 'N/A';
        
        // Toggle map display based on vehicle availability
        mapContainer.style.display = isVehicleAvailable(data) ? 'block' : 'none';
    }

    function initializeMap(data) {
        if (!isVehicleAvailable(data)) {
            mapContainer.style.display = 'none';
            return;
        }

        mapContainer.style.display = 'block';

        if (map) {
            map.remove();
        }

        // Initialize new map
        map = L.map('map-container').setView([data.position.lat, data.position.lng], 14);

        // Add tile layer
        L.tileLayer(`https://api.mapy.cz/v1/maptiles/basic/256/{z}/{x}/{y}?apikey=${API_KEY}`, {
            minZoom: 0,
            maxZoom: 19,
            attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
        }).addTo(map);

        // Add logo control
        const LogoControl = L.Control.extend({
            options: {
                position: 'bottomleft',
            },
            onAdd: function (map) {
                const container = L.DomUtil.create('div');
                const link = L.DomUtil.create('a', '', container);
                link.setAttribute('href', 'http://mapy.cz/');
                link.setAttribute('target', '_blank');
                link.innerHTML = '<img src="https://api.mapy.cz/img/api/logo.svg" />';
                L.DomEvent.disableClickPropagation(link);
                return container;
            },
        });

        new LogoControl().addTo(map);

        // Add vehicle marker
        const delayColor = getDelayColor(data.info.delay);
        const busIcon = L.IconMaterial.icon({
            icon: 'directions_bus',
            iconColor: '#ffffff',
            markerColor: delayColor,
            outlineColor: 'white',
            outlineWidth: 1,
            iconSize: [31, 42]
        });

        vehicleMarker = L.marker([data.position.lat, data.position.lng], {icon: busIcon}).addTo(map);

        // Add trip geometry if available
        if (data.tripGeometry && data.tripGeometry.length > 0) {
            tripPath = L.polyline(data.tripGeometry.map(point => [point.lat, point.lng]), {
                color: '#3b82f6',
                weight: 4,
                opacity: 0.8
            }).addTo(map);
            map.fitBounds(tripPath.getBounds());
        }

        // Add stops on trip if available
        if (data.stopsOnTrip && data.stopsOnTrip.length > 0) {
            data.stopsOnTrip.forEach(stop => {
                L.marker([stop.lat, stop.lng], {
                    icon: L.IconMaterial.icon({
                        icon: 'place',
                        iconColor: '#ffffff',
                        markerColor: '#64748b',
                        outlineColor: 'white',
                        outlineWidth: 1,
                        iconSize: [25, 41]
                    })
                }).addTo(map)
                    .bindPopup(`<b>${stop.name}</b>`);
            });
        }
    }

    // Update just the vehicle position on the map
    function updateVehiclePosition(data) {
        if (!isVehicleAvailable(data) || !map) return;
        
        // Update marker position
        const delayColor = getDelayColor(data.info.delay);
        
        if (vehicleMarker) {
            // Update existing marker position
            vehicleMarker.setLatLng([data.position.lat, data.position.lng]);
            
            // Update icon color based on delay
            const busIcon = L.IconMaterial.icon({
                icon: 'directions_bus',
                iconColor: '#ffffff',
                markerColor: delayColor,
                outlineColor: 'white',
                outlineWidth: 1,
                iconSize: [31, 42]
            });
            
            vehicleMarker.setIcon(busIcon);
        }
    }

    function getDelayColor(delay) {
        if (delay <= -1) return 'var(--early)';     // Purple for early
        if (delay <= 4) return 'var(--success)';    // Green for on time (0-4 min)
        if (delay <= 9) return 'var(--warning)';    // Yellow for slight delay (5-9 min)
        return 'var(--error)';                      // Red for significant delay (10+ min)
    }

    function getDelayText(delay) {
        if (delay < 0) return `Předčasný odjezd: ${Math.abs(delay)} min`;
        if (delay === 0) return 'Včas';
        return `Zpoždění: ${delay} min`;
    }

    function startAutoUpdate(trip, line) {
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        
        updateInterval = setInterval(() => {
            // Call with isUpdate=true to avoid showing loading screen
            getVehicleInfo(trip, line, true);
        }, 60000); // Update every minute
    }
    </script>
</body>
</html>
