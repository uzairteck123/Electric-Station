<?php
// proxy.php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

$action = $_GET['action'] ?? '';

// ===== Search / Suggest (Nominatim) =====
if ($action === 'search' || $action === 'suggest') {
    $query = trim($_GET['query'] ?? '');
    if ($query === '') { echo json_encode([]); exit; }

    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($query) . "&limit=6&accept-language=en";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "EV-Charger-Explorer/1.0 (+contact@example.com)");
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!is_array($data)) $data = [];
    echo json_encode($data);
    exit;
}

// ===== Load Stations (OpenChargeMap) =====
if ($action === 'load_stations') {
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 0;
    $lon = isset($_GET['lon']) ? floatval($_GET['lon']) : 0;
    $maxDistance = isset($_GET['distance']) ? floatval($_GET['distance']) : 50; // default 50 km
    $maxResults = isset($_GET['maxresults']) ? intval($_GET['maxresults']) : 200;

    if (!$lat || !$lon) { echo json_encode([]); exit; }

    $apiKey = 'bdc7297d-6eef-411b-8e98-36ffce99bb27'; // OpenChargeMap API key

    $url = "https://api.openchargemap.io/v3/poi/?output=json&latitude={$lat}&longitude={$lon}&maxresults={$maxResults}&distance={$maxDistance}&distanceunit=KM";
    if (!empty($apiKey)) $url .= "&key=" . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "EV-Charger-Explorer/1.0 (+contact@example.com)");
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) { echo json_encode(['error' => "Curl error: $err"]); exit; }
    if ($httpCode >= 400) { echo json_encode(['error' => "OpenChargeMap returned HTTP {$httpCode}"]); exit; }

    $raw = json_decode($response, true);
    if (!is_array($raw)) $raw = [];

    // ===== Helper: Haversine distance calculation =====
    function distanceKm($lat1, $lon1, $lat2, $lon2){
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

    $stations = [];
    foreach ($raw as $poi) {
        if (empty($poi['AddressInfo']) || !isset($poi['AddressInfo']['Latitude']) || !isset($poi['AddressInfo']['Longitude'])) continue;

        $addr = $poi['AddressInfo'];
        $latStation = floatval($addr['Latitude']);
        $lonStation = floatval($addr['Longitude']);
        $distKm = distanceKm($lat, $lon, $latStation, $lonStation);

        // Only include stations within the requested distance
        if ($distKm > $maxDistance) continue;

        $connections = $poi['Connections'] ?? [];
        $ports = [];
        foreach ($connections as $c) {
            $ctype = $c['ConnectionType']['Title'] ?? ($c['Level']['Title'] ?? 'Standard');
            $power = isset($c['PowerKW']) ? (string)$c['PowerKW'] . " kW" : 'N/A';
            $ports[] = [
                'type' => $ctype,
                'speed' => $power,
                'avail' => isset($c['Quantity']) ? intval($c['Quantity']) : rand(1,2),
                'total' => isset($c['Quantity']) ? intval($c['Quantity']) : rand(1,3)
            ];
        }

        $stations[] = [
            'id' => $poi['ID'] ?? uniqid(),
            'title' => $addr['Title'] ?? ($addr['AddressLine1'] ?? 'Unknown Station'),
            'address' => trim(($addr['AddressLine1'] ?? '') . ' ' . ($addr['Town'] ?? '') . ' ' . ($addr['StateOrProvince'] ?? '')),
            'lat' => $latStation,
            'lon' => $lonStation,
            'distance' => round($distKm,1),
            'power' => $ports[0]['speed'] ?? 'N/A',
            'fee' => isset($poi['UsageCost']) ? $poi['UsageCost'] : ($poi['UsageType']['Title'] ?? '$0.00'),
            'schedule' => $poi['AccessComments'] ?? '24/7',
            'ports' => $ports,
            'state' => 'ready'
        ];
    }

    echo json_encode(array_values($stations));
    exit;
}
// ===== Default =====
echo json_encode([]);
