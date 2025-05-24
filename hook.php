<?php
$config = require 'config.php';

function logPrint($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] $msg\n";
}

// Verifikasi PubSubHubbub
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo $_GET['hub_challenge'] ?? 'No challenge';
    exit;
}

// Ambil XML notifikasi dari YouTube
$xml = file_get_contents('php://input');
$feed = simplexml_load_string($xml);
$yt = $feed->entry->children('http://www.youtube.com/xml/schemas/2015');

if (!isset($yt->videoId)) exit;

$videoId = (string)$yt->videoId;

// === Baca dan Bersihkan sent.json ===
$sentFile = __DIR__ . '/sent.json';
$sent = file_exists($sentFile) ? json_decode(file_get_contents($sentFile), true) : [];
$maxSent = 100;

if (count($sent) > $maxSent) {
    $sent = array_slice($sent, -$maxSent);
    file_put_contents($sentFile, json_encode($sent));
}

// Jika sudah pernah dikirim, keluar
if (in_array($videoId, $sent)) exit;

// === Cek apakah video ini LIVE ===
$apiUrl = "https://www.googleapis.com/youtube/v3/videos?part=snippet,liveStreamingDetails&id=$videoId&key={$config['youtube_api_key']}";
$response = json_decode(file_get_contents($apiUrl), true);

if (empty($response['items'])) exit;

$video = $response['items'][0];
if (($video['snippet']['liveBroadcastContent'] ?? '') !== 'live') exit;

// === Kirim Pesan ke Telegram ===
$title = $video['snippet']['title'];
$thumbnail = $video['snippet']['thumbnails']['high']['url'];
$url = 'https://www.youtube.com/watch?v=' . $videoId;

$caption = "📺 *$title*\n🔴 [Tonton Live Sekarang]($url)";
$send = file_get_contents("https://api.telegram.org/bot{$config['telegram_token']}/sendPhoto?" . http_build_query([
    'chat_id' => $config['telegram_chat_id'],
    'photo' => $thumbnail,
    'caption' => $caption,
    'parse_mode' => 'Markdown',
]));

$sendResult = json_decode($send, true);
if (isset($sendResult['result']['message_id'])) {
    $msgId = $sendResult['result']['message_id'];

    // Simpan ke cache.json
    $cacheFile = $config['cache_file'];
    $cache = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];
    $cache[] = [
        'message_id' => $msgId,
        'timestamp' => time()
    ];
    file_put_contents($cacheFile, json_encode($cache));
}

// Simpan videoId ke sent.json
$sent[] = $videoId;
file_put_contents($sentFile, json_encode($sent));

// === HAPUS PESAN TELEGRAM YANG SUDAH LEWAT 1 JAM ===
$cacheFile = $config['cache_file'];
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true);
    $now = time();
    $keep = [];

    foreach ($cache as $item) {
        if ($now - $item['timestamp'] >= 3600) {
            // Hapus dari Telegram
            file_get_contents("https://api.telegram.org/bot{$config['telegram_token']}/deleteMessage?" . http_build_query([
                'chat_id' => $config['telegram_chat_id'],
                'message_id' => $item['message_id']
            ]));
        } else {
            $keep[] = $item;
        }
    }

    // Tulis ulang cache yang belum expired
    file_put_contents($cacheFile, json_encode($keep));
}
