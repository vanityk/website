<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112'; // 你的授權碼
$cacheFile = __DIR__ . '/weather_cache_7days.json';
$cacheTime = 3600;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-033?Authorization={$apiKey}&locationName=" . urlencode('六腳鄉');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['success']) || $data['success'] !== 'true') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => '授權失敗或連線異常', 'raw' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// 遞迴尋找六腳鄉的 WeatherElement
$elements = null;

function findLocationElements($node) {
    if (!is_array($node)) return null;

    // 檢查當前節點是否就是六腳鄉的物件
    $name = $node['LocationName'] ?? $node['locationName'] ?? '';
    if (trim($name) === '六腳鄉') {
        return $node['WeatherElement'] ?? $node['weatherElement'] ?? null;
    }

    // 若不是，繼續深入搜尋子陣列
    foreach ($node as $key => $child) {
        if (is_array($child)) {
            $result = findLocationElements($child);
            if ($result !== null) return $result;
        }
    }
    return null;
}

$elements = findLocationElements($data['records'] ?? []);

// 如果搜尋六腳鄉沒找到（可能 API 已經自動濾過且沒放 LocationName），則直接抓第一組 WeatherElement
if (!$elements) {
    function findAnyWeatherElements($node) {
        if (!is_array($node)) return null;
        if (isset($node['WeatherElement']) && is_array($node['WeatherElement'])) return $node['WeatherElement'];
        if (isset($node['weatherElement']) && is_array($node['weatherElement'])) return $node['weatherElement'];
        foreach ($node as $child) {
            if (is_array($child)) {
                $res = findAnyWeatherElements($child);
                if ($res !== null) return $res;
            }
        }
        return null;
    }
    $elements = findAnyWeatherElements($data['records'] ?? []);
}

// 仍找不到要素時輸出診斷
if (!$elements) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => '完全解析不到 WeatherElement',
        'debug_structure' => array_keys($data['records']['Locations'][0] ?? [])
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 整理 7 天預報資料
$dailyMap = [];

foreach ($elements as $elem) {
    $elemName = $elem['ElementName'] ?? $elem['elementName'] ?? '';
    $timeList = $elem['Time'] ?? $elem['time'] ?? [];

    foreach ($timeList as $t) {
        $startTime = $t['StartTime'] ?? $t['startTime'] ?? '';
        $date = substr($startTime, 5, 5); // MM-DD
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

        if (in_array($elemName, ['MinT', 'minT'])) {
            $dailyMap[$date]['min_t'] = $val;
        } elseif (in_array($elemName, ['MaxT', 'maxT'])) {
            $dailyMap[$date]['max_t'] = $val;
        } elseif (in_array($elemName, ['WeatherDescription', 'weatherDescription', 'Wx', 'wx'])) {
            $parts = explode('。', $val);
            $dailyMap[$date]['desc'] = mb_substr($parts[0] ?? '多雲', 0, 6);
            $dailyMap[$date]['full_desc'] = $val;
        } elseif (in_array($elemName, ['PoP12h', 'pop12h', 'PoP6h']) && trim($val) !== '') {
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