<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 填入你的授權碼
$cacheFile = __DIR__ . '/weather_cache_7days.json';
$cacheTime = 3600; // 7 天預報快取 1 小時即可

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// F-D0047-033 為嘉義縣未來 1 週預報
$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-033?Authorization={$apiKey}&locationName=" . urlencode('六腳鄉') . "&elementName=WeatherDescription,MinT,MaxT,PoP12h";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$curlError = curl_error($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '連線錯誤: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);
$elements = null;

$locations = $data['records']['Locations'] ?? $data['records']['locations'] ?? [];
if (!empty($locations)) {
    $locList = $locations[0]['Location'] ?? $locations[0]['location'] ?? [];
    foreach ($locList as $loc) {
        $name = $loc['LocationName'] ?? $loc['locationName'] ?? '';
        if ($name === '六腳鄉' || empty($name)) {
            $elements = $loc['WeatherElement'] ?? $loc['weatherElement'] ?? [];
            break;
        }
    }
}

if (!$elements) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '查無六腳鄉一週氣象資料'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 整理出以「天」為單位的數據
$dailyMap = [];

foreach ($elements as $elem) {
    $elemName = $elem['ElementName'] ?? $elem['elementName'] ?? '';
    $timeList = $elem['Time'] ?? $elem['time'] ?? [];

    foreach ($timeList as $t) {
        $startTime = $t['StartTime'] ?? $t['startTime'] ?? '';
        $date = substr($startTime, 5, 5); // 取得 MM-DD
        if (!$date) continue;

        if (!isset($dailyMap[$date])) {
            $dailyMap[$date] = [
                'date' => $date,
                'min_t' => '--',
                'max_t' => '--',
                'desc' => '多雲',
                'rain' => '0%'
            ];
        }

        $val = $t['ElementValue'][0]['Value'] ?? $t['ElementValue'][0]['value'] ?? '';

        if ($elemName === 'MinT') {
            $dailyMap[$date]['min_t'] = $val;
        } elseif ($elemName === 'MaxT') {
            $dailyMap[$date]['max_t'] = $val;
        } elseif ($elemName === 'WeatherDescription') {
            // 擷取簡短天氣（例如「陰短暫雨」）
            $shortDesc = explode('。', $val)[0] ?? '多雲';
            $dailyMap[$date]['desc'] = mb_substr($shortDesc, 0, 6);
        } elseif ($elemName === 'PoP12h' && $val !== ' ') {
            $dailyMap[$date]['rain'] = $val . '%';
        }
    }
}

// 截取前 7 天資料
$forecast7Days = array_slice(array_values($dailyMap), 0, 7);

$result = [
    'status' => 'success',
    'location' => '嘉義縣六腳鄉',
    'forecast' => $forecast7Days,
    'updated_at' => date('Y-m-d H:i')
];

$output = json_encode($result, JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $output);
echo $output;