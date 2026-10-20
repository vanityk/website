<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 換成你的完整金鑰
$cacheFile = __DIR__ . '/weather_cache.json';
$cacheTime = 1800; // 快取 30 分鐘

// 若有快取且未過期，直接回傳快取
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
    echo json_encode(['status' => 'error', 'message' => 'cURL 錯誤: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['success']) || $data['success'] !== 'true') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '氣象署授權失敗或查詢錯誤', 'raw' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 動態定位六腳鄉的 weatherElement
$elements = null;

// 方式 1：標準結構 locations[0]['location'][0]
if (isset($data['records']['locations'][0]['location'])) {
    foreach ($data['records']['locations'][0]['location'] as $loc) {
        if (($loc['locationName'] ?? '') === '六腳鄉' || empty($loc['locationName'])) {
            $elements = $loc['weatherElement'] ?? null;
            break;
        }
    }
}

// 方式 2：扁平結構 records['location']
if (!$elements && isset($data['records']['location'])) {
    foreach ($data['records']['location'] as $loc) {
        if (($loc['locationName'] ?? '') === '六腳鄉' || empty($loc['locationName'])) {
            $elements = $loc['weatherElement'] ?? null;
            break;
        }
    }
}

// 若依然找不到元素
if (!$elements) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '無法解析天氣要素列表',
        'records_keys' => isset($data['records']) ? array_keys($data['records']) : 'no records'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 提取數值
$weatherDesc = '晴朗';
$temperature = '--';
$rainProb = '0%';

foreach ($elements as $elem) {
    $name = $elem['elementName'] ?? '';
    // 取第一個時間區段的值
    $val = $elem['time'][0]['elementValue'][0]['value'] ?? null;

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

// 寫入快取
@file_put_contents($cacheFile, $output);

echo $output;