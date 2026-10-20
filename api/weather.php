<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 你的氣象署授權碼
$cacheFile = __DIR__ . '/weather_cache_7days.json';
$cacheTime = 3600;

// 若有快取且未過期，直接回傳
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// 請求 7 天一週預報 (F-D0047-033: 嘉義縣未來 1 週天氣預報)
$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-033?Authorization={$apiKey}&locationName=" . urlencode('六腳鄉');

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

if (!isset($data['success']) || $data['success'] !== 'true') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '氣象署授權失敗或查詢錯誤', 'raw' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 自動找出包含六腳鄉的物件
$elements = null;

// 1. 抓出所有 locations 群組
$locationsGroup = $data['records']['Locations'] ?? $data['records']['locations'] ?? [];
if (!empty($locationsGroup) && is_array($locationsGroup)) {
    foreach ($locationsGroup as $locGroup) {
        $locList = $locGroup['Location'] ?? $locGroup['location'] ?? [];
        foreach ($locList as $loc) {
            $name = $loc['LocationName'] ?? $loc['locationName'] ?? '';
            if ($name === '六腳鄉' || empty($name)) {
                $elements = $loc['WeatherElement'] ?? $loc['weatherElement'] ?? [];
                break 2;
            }
        }
    }
}

// 2. 備用搜尋：若 records 直接掛 location
if (!$elements && isset($data['records']['location'])) {
    foreach ($data['records']['location'] as $loc) {
        $name = $loc['LocationName'] ?? $loc['locationName'] ?? '';
        if ($name === '六腳鄉' || empty($name)) {
            $elements = $loc['WeatherElement'] ?? $loc['weatherElement'] ?? [];
            break;
        }
    }
}

if (!$elements) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '解析六腳鄉要素失敗',
        'raw_keys' => isset($data['records']) ? array_keys($data['records']) : []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 整理出以天為單位的氣象數據
$dailyMap = [];

foreach ($elements as $elem) {
    $elemName = $elem['ElementName'] ?? $elem['elementName'] ?? '';
    $timeList = $elem['Time'] ?? $elem['time'] ?? [];

    foreach ($timeList as $t) {
        $startTime = $t['StartTime'] ?? $t['startTime'] ?? '';
        $date = substr($startTime, 5, 5); // 取出 MM-DD
        if (!$date) continue;

        if (!isset($dailyMap[$date])) {
            $dailyMap[$date] = [
                'date' => $date,
                'min_t' => '--',
                'max_t' => '--',
                'desc' => '多雲',
                'full_desc' => '',
                'rain' => '0%',
                'uvi' => '正常',
                'comfort' => '舒適',
                'wind' => '微風'
            ];
        }

        $valObj = $t['ElementValue'][0] ?? $t['elementValue'][0] ?? [];
        $val = $valObj['Value'] ?? $valObj['value'] ?? '';

        // 依要素名稱存入
        if (in_array($elemName, ['MinT', 'minT'])) {
            $dailyMap[$date]['min_t'] = $val;
        } elseif (in_array($elemName, ['MaxT', 'maxT'])) {
            $dailyMap[$date]['max_t'] = $val;
        } elseif (in_array($elemName, ['WeatherDescription', 'weatherDescription', 'Wx'])) {
            $parts = explode('。', $val);
            $dailyMap[$date]['desc'] = mb_substr($parts[0] ?? '多雲', 0, 6);
            $dailyMap[$date]['full_desc'] = $val;
        } elseif (in_array($elemName, ['PoP12h', 'pop12h', 'PoP6h']) && $val !== ' ') {
            $dailyMap[$date]['rain'] = $val . '%';
        } elseif (in_array($elemName, ['UVI', 'uvi'])) {
            $dailyMap[$date]['uvi'] = $val;
        } elseif (in_array($elemName, ['CI', 'ci'])) {
            $dailyMap[$date]['comfort'] = $val;
        } elseif (in_array($elemName, ['WS', 'ws'])) {
            $dailyMap[$date]['wind'] = $val . ' m/s';
        }
    }
}

$forecastList = array_slice(array_values($dailyMap), 0, 7);

$result = [
    'status' => 'success',
    'location' => '嘉義縣六腳鄉',
    'forecast' => $forecastList,
    'updated_at' => date('Y-m-d H:i')
];

$output = json_encode($result, JSON_UNESCAPED_UNICODE);
@file_put_contents($cacheFile, $output);
echo $output;