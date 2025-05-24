<?php
$channelIds = ['UCJXXX...', 'UCJXXX...']; // ganti dengan channel id YouTube
$callbackUrl = 'https://DOMAINANDA.COM/hook.php'; // ganti dengan alamat hook
$hubUrl = 'https://pubsubhubbub.appspot.com/subscribe';

foreach ($channelIds as $channelId) {
    $topic = 'https://www.youtube.com/xml/feeds/videos.xml?channel_id=' . $channelId;
    
    $data = http_build_query([
        'hub.mode' => 'subscribe',
        'hub.topic' => $topic,
        'hub.callback' => $callbackUrl,
        'hub.verify' => 'async',
        'hub.verify_token' => 'verify_token_safe',
    ]);

    $opts = ['http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $data
    ]];

    $context = stream_context_create($opts);
    $result = file_get_contents($hubUrl, false, $context);
    echo "Subscribe to $channelId: " . ($result !== false ? 'Success' : 'Failed') . "\n";
}
