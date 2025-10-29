<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Načtení konfigurace
require_once 'config.php';

$apiBaseUrl = 'https://dukapi.sap1k.cz';

// Funkce pro volání API
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
    return json_decode($response, true);
}

// Kontrola ID parametru
if (!isset($_GET['id'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Chybí ID vozidla'
    ]);
    exit;
}

$id = strval($_GET['id']);

// Načtení JSON souboru s informacemi o vozidlech
$jsonFile = file_get_contents('bus_data.json');
if ($jsonFile === false) {
    echo json_encode([
        'error' => true,
        'message' => 'Nepodařilo se načíst data vozidel'
    ]);
    exit;
}

$busData = json_decode($jsonFile, true);
if ($busData === null) {
    echo json_encode([
        'error' => true,
        'message' => 'Chyba při zpracování dat vozidel'
    ]);
    exit;
}

// Kontrola existence vozidla v JSON datech
if (!isset($busData[$id])) {
    echo json_encode([
        'error' => true,
        'message' => 'Vozidlo s tímto ID nebylo nalezeno'
    ]);
    exit;
}

// Získání informací o vozidle
$vehicleInfo = callApi('/GetVhcInfoByID', 'POST', ['ID' => $id]);

// Získání detailů o vozidle
$vehicleDetails = callApi('/GetVhcDetailsByID', 'POST', ['ID' => $id]);

// Získání polohy vozidla s použitím informací z GetVhcInfoByID
$vehiclePos = null;
if ($vehicleInfo && isset($vehicleInfo['line_displayed']) && isset($vehicleInfo['trip'])) {
    $vehiclePos = callApi('/GetVhcPos', 'POST', [
        'vhc_id' => intval($id),
        'line_displayed' => $vehicleInfo['line_displayed'],
        'trip' => $vehicleInfo['trip']
    ]);
}

// Sestavení odpovědi
$response = [
    'error' => false,
    'data' => [
        'id' => $id,
        'image_url' => $busData[$id]['image'],
        'seznam_link' => $busData[$id]['url'],
        'details' => [
            'model' => $vehicleDetails['model'] ?? $busData[$id]['model'] ?? 'Neznámý model',
            'agency' => $vehicleDetails['agency'] ?? $vehicleInfo['agency'] ?? 'Neznámý dopravce',
            'year_of_manufacture' => $vehicleDetails['year_of_manufacture'] ?? null,
            'accessible' => $vehicleDetails['accessible'] ?? $vehicleInfo['accessible'] ?? false,
            'contactless_payments' => $vehicleDetails['contactless_payments'] ?? $busData[$id]['contactless_payments'] ?? false,
            'air_conditioning' => $vehicleDetails['air_conditioning'] ?? $busData[$id]['air_conditioning'] ?? false,
            'alternate_fuel' => $vehicleDetails['alternate_fuel'] ?? $busData[$id]['alternate_fuel'] ?? false,
            'usb_chargers' => $vehicleDetails['usb_chargers'] ?? $busData[$id]['usb_chargers'] ?? false
        ],
        'vehicle_info' => [
            'on_trip' => $vehicleInfo['on_trip'] ?? false,
            'line_displayed' => $vehicleInfo['line_displayed'] ?? null,
            'trip' => $vehicleInfo['trip'] ?? null,
            'is_train' => $vehicleInfo['is_train'] ?? false,
            'end_stop' => $vehicleInfo['end_stop'] ?? null,
            'current_stop' => $vehicleInfo['current_stop'] ?? null,
            'current_stop_sequence' => $vehicleInfo['current_stop_sequence'] ?? null,
            'delay' => $vehicleInfo['delay'] ?? null,
            'agency' => $vehicleInfo['agency'] ?? null,
            'accessible' => $vehicleInfo['accessible'] ?? false,
            'last_ping' => $vehicleInfo['last_ping'] ?? null
        ],
        'position' => null
    ]
];

// Přidání lokačních dat, pokud jsou dostupná
if ($vehiclePos && isset($vehiclePos[0])) {
    $response['data']['position'] = [
        'latitude' => $vehiclePos[0]['lat'] ?? null,
        'longitude' => $vehiclePos[0]['lng'] ?? null
    ];
}

// Vrácení dat
echo json_encode($response, JSON_PRETTY_PRINT);
?>