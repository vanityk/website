<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$apiKey = 'CWA-67B8A854-DD0D-44DD-B1CB-D0C31BD6E112';
$cacheFile = __DIR__ . '/weather_cache_7days.json';
$cacheTime = 3600;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    echo file_get_contents($cacheFile);
    exit;
}

// 關鍵修正：改用 035（嘉義縣未來 1 週預報）
$url = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/F-D0047-035?Authorization={$apiKey}&locationName=" . urlencode('六腳鄉');

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
    echo json_encode(['status' => 'error', 'message' => '授權失敗或連線異常'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 遞迴尋找六腳鄉的 WeatherElement
function findWeatherElements($node) {
    if (!is_array($node)) return null;

    $name = $node['LocationName'] ?? $node['locationName'] ?? '';
    if (trim($name) === '六腳鄉') {
        return $node['WeatherElement'] ?? $node['weatherElement'] ?? null;
    }

    foreach ($node as $child) {
        if (is_array($child)) {
            $res = findWeatherElements($child);
            if ($res !== null) return $res;
        }
    }
    return null;
}

$elements = findWeatherElements($data['records'] ?? []);

if (!$elements) {
    function findAnyWeatherElements($node) {
        if (!is_array($node)) return null;
        if (!empty($node['WeatherElement']) && is_array($node['WeatherElement'])) return $node['WeatherElement'];
        if (!empty($node['weatherElement']) && is_array($node['weatherElement'])) return $node['weatherElement'];
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

if (!$elements) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '找不到氣象要素'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 萬能取值：相容氣溫、字串與各類巢狀結構
function extractVal($timeItem) {
    $valContainer = $timeItem['ElementValue'] ?? $timeItem['elementValue'] ?? [];
    if (!is_array($valContainer)) return '';
    $first = $valContainer[0] ?? [];
    if (!is_array($first)) return (string)$first;

    foreach (['Value', 'value', 'Weather', 'weather', 'Temperature', 'temperature', 'MinTemperature', 'MaxTemperature'] as $k) {
        if (isset($first[$k]) && trim((string)$first[$k]) !== '') {
            return (string)$first[$k];
        }
    }
    foreach ($first as $v) {
        if (is_scalar($v) && trim((string)$v) !== '') return (string)$v;
    }
    return '';
}

$dailyMap = [];

// 1. 掃描所有時間建立未來日期
foreach ($elements as $elem) {
    $timeList = $elem['Time'] ?? $elem['time'] ?? [];
    foreach ($timeList as $t) {
        $startTime = $t['StartTime'] ?? $t['startTime'] ?? $t['dataTime'] ?? '';
        $date = substr($startTime, 5, 5);
        if ($date && !isset($dailyMap[$date])) {
            $dailyMap[$date] = [
                'date' => $date,
                'min_t' => '--',
                'max_t' => '--',
                'desc' => '多雲',
                'full_desc' => '天氣穩定，早晚留意日夜溫差。',
                'rain' => '-',
                'uvi' => '普通',
                'comfort' => '舒適',
                'wind' => '微風'
            ];
        }
    }
}

// 2. 匹配填值
foreach ($elements as $elem) {
    $elemName = $elem['ElementName'] ?? $elem['elementName'] ?? '';
    $timeList = $elem['Time'] ?? $elem['time'] ?? [];

    foreach ($timeList as $t) {
        $startTime = $t['StartTime'] ?? $t['startTime'] ?? $t['dataTime'] ?? '';
        $date = substr($startTime, 5, 5);
        if (!$date || !isset($dailyMap[$date])) continue;

        $val = extractVal($t);
        if ($val === '') continue;

        // 最低溫
        if (in_array($elemName, ['MinT', 'minT', '最低溫度', 'MinTemperature'])) {
            if ($dailyMap[$date]['min_t'] === '--' || (is_numeric($val) && (float)$val < (float)$dailyMap[$date]['min_t'])) {
                $dailyMap[$date]['min_t'] = $val;
            }
        }
        // 最高溫
        elseif (in_array($elemName, ['MaxT', 'maxT', '最高溫度', 'MaxTemperature'])) {
            if ($dailyMap[$date]['max_t'] === '--' || (is_numeric($val) && (float)$val > (float)$dailyMap[$date]['max_t'])) {
                $dailyMap[$date]['max_t'] = $val;
            }
        }
        // 天氣現象
        elseif (in_array($elemName, ['Wx', 'wx', 'WeatherDescription', 'weatherDescription', '天氣現象'])) {
            $parts = explode('。', $val);
            $mainText = trim($parts[0] ?? '多雲');
            if ($mainText !== '') {
                $dailyMap[$date]['desc'] = $mainText;
            }
            $dailyMap[$date]['full_desc'] = $val;
        }
        // 降雨機率
        elseif (in_array($elemName, ['PoP12h', 'pop12h', 'PoP6h', '12小時降雨機率', '降雨機率'])) {
            $dailyMap[$date]['rain'] = $val . '%';
        }
        // 紫外線
        elseif (in_array($elemName, ['UVI', 'uvi', '紫外線指數'])) {
            $dailyMap[$date]['uvi'] = $val;
        }
        // 舒適度
        elseif (in_array($elemName, ['CI', 'ci', '舒適度指數'])) {
            $dailyMap[$date]['comfort'] = $val;
        }
        // 風速
        elseif (in_array($elemName, ['WS', 'ws', '風速'])) {
            $dailyMap[$date]['wind'] = $val . ' m/s';
        }
    }
}

ksort($dailyMap);

// 取滿 7 天
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