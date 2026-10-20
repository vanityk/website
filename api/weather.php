<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$cacheFile = __DIR__ . '/weather_cache.json';$cacheTime = 1800; // 快取 30 分鐘 (1800 秒)

// 若快取存在且未過期，直接回傳快取
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) <$cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 填入你的氣象署授權碼
$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-031?Authorization={$apiKey}&locationName=" . urlencode('六腳鄉') . "&elementName=Wx,T,PoP6h";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,$url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['records']['locations'][0]['location'][0]['weatherElement'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '氣象資料解析失敗']);
    exit;
}

$elements =$data['records']['locations'][0]['location'][0]['weatherElement'];

// 整理出當前時段的核心天氣數據
$weatherDesc = '晴朗';
$temperature = '--';$rainProb = '0%';

foreach ($elements as$elem) {
    if ($elem['elementName'] === 'Wx') {
        $weatherDesc =$elem['time'][0]['elementValue'][0]['value'] ?? '晴朗';
    }
    if ($elem['elementName'] === 'T') {
        $temperature = ($elem['time'][0]['elementValue'][0]['value'] ?? '--') . '°C';
    }
    if ($elem['elementName'] === 'PoP6h') {
        $rainProb = ($elem['time'][0]['elementValue'][0]['value'] ?? '0') . '%';
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

$outputJson = json_encode($result, JSON_UNESCAPED_UNICODE);

// 寫入快取檔
file_put_contents($cacheFile,$outputJson);

echo $outputJson;