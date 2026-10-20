<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 務必確認這是你在氣象署複製的授權碼
$cacheFile = __DIR__ . '/weather_cache.json';
$cacheTime = 1800; // 快取 30 分鐘

// 若有快取且未過期，直接回傳
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// 建立標準 Query 字串
$queryParams = http_build_query([
    'Authorization' => $apiKey,
    'locationName' => '六腳鄉',
    'elementName' => 'Wx,T,PoP6h'
]);

$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-031?" . $queryParams;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 避免樹莓派 CA 憑證過期造成連線中斷
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'cURL 連線錯誤: ' . $curlError], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

// 檢查 API 是否回報認證失敗
if (isset($data['success']) && $data['success'] === 'false') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '氣象署授權失敗: ' . ($data['message'] ?? '金鑰錯誤')], JSON_UNESCAPED_UNICODE);
    exit;
}

// 支援單層或巢狀 locations 的容錯解析
$locationData = null;
if (isset($data['records']['locations'][0]['location'][0]['weatherElement'])) {
    $locationData = $data['records']['locations'][0]['location'][0]['weatherElement'];
} elseif (isset($data['records']['location'][0]['weatherElement'])) {
    $locationData = $data['records']['location'][0]['weatherElement'];
}

if (!$locationData) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '查無六腳鄉氣象資料，請確認氣象署回傳結構',
        'debug' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$weatherDesc = '晴朗';
$temperature = '--';
$rainProb = '0%';

foreach ($locationData as $elem) {
    $name = $elem['elementName'];
    $val = $elem['time'][0]['elementValue'][0]['value'] ?? null;

    if ($name === 'Wx' && $val) {
        $weatherDesc = $val;
    } elseif ($name === 'T' && $val) {
        $temperature = $val . '°C';
    } elseif ($name === 'PoP6h' && $val) {
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
file_put_contents($cacheFile, $output);
echo $output;