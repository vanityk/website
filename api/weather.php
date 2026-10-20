<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 換成你的完整授權碼
$cacheFile = __DIR__ . '/weather_cache.json';
$cacheTime = 1800; // 快取 30 分鐘

// 若有快取且未過期，直接回傳
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-031?Authorization={$apiKey}&locationName=" . urlencode('六腳鄉') . "&elementName=Wx,T,PoP6h";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$curlError = curl_error($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'cURL 連線錯誤: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

// 提取氣象署的大寫鍵名：records -> Locations -> Location -> WeatherElement
$elements = null;

// 1. 抓取 Locations 陣列（支援大小寫相容）
$locations = $data['records']['Locations'] ?? $data['records']['locations'] ?? null;

if (!empty($locations) && is_array($locations)) {
    $firstLocGroup = $locations[0] ?? [];
    $locationList = $firstLocGroup['Location'] ?? $firstLocGroup['location'] ?? [];

    foreach ($locationList as $loc) {
        $name = $loc['LocationName'] ?? $loc['locationName'] ?? '';
        if ($name === '六腳鄉' || empty($name)) {
            $elements = $loc['WeatherElement'] ?? $loc['weatherElement'] ?? null;
            break;
        }
    }
}

if (!$elements) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '解析六腳鄉要素失敗',
        'raw_sample' => $locations[0] ?? '空陣列'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 整理天氣資訊
$weatherDesc = '晴朗';
$temperature = '--';
$rainProb = '0%';

foreach ($elements as $elem) {
    $name = $elem['ElementName'] ?? $elem['elementName'] ?? '';
    $timeList = $elem['Time'] ?? $elem['time'] ?? [];
    
    // 取當前時段的第一組數值
    $valObj = $timeList[0]['ElementValue'][0] ?? $timeList[0]['elementValue'][0] ?? [];
    $val = $valObj['Value'] ?? $valObj['value'] ?? null;

    if ($name === 'Wx' && $val !== null) {
        $weatherDesc = $val;
    } elseif ($name === 'T' && $val !== null) {
        $temperature = $val . '°C';
    } elseif ($name === 'PoP6h' && $val !== null) {
        $rainProb = $val . '%';
    }
}

$result = [
    'status' => 'success',
    'location' => '嘉義縣六腳鄉',
    'weather' => $weatherDesc,
    'temp' => $temperature,
    'rain' => $rainProb,
    'updated_at' => date('Y-m-d H:i')
];

$output = json_encode($result, JSON_UNESCAPED_UNICODE);

// 寫入快取檔
@file_put_contents($cacheFile, $output);

echo $output;