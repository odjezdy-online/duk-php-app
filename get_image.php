<?php
header('Content-Type: application/json; charset=utf-8');

// Kontrola ID parametru
if (!isset($_GET['id'])) {
    echo json_encode([
        'error' => true,
        'message' => 'Chybí ID vozidla'
    ]);
    exit;
}

$id = strval($_GET['id']); // Převedeme na string, protože v JSONu jsou klíče jako stringy

// Načtení JSON souboru
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

// Kontrola existence vozidla
if (!isset($busData[$id])) {
    echo json_encode([
        'error' => true,
        'message' => 'Vozidlo s tímto ID nebylo nalezeno'
    ]);
    exit;
}

// Vrácení dat o vozidle
echo json_encode([
    'error' => false,
    'data' => [
        'id' => $id,
        'image_url' => $busData[$id]['image'],
        'seznam_link' => $busData[$id]['url']
    ]
]);
?>