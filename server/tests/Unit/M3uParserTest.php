<?php

use App\Enums\SourceType;
use App\Services\M3uImporter;

it('parses EXTINF attributes, names and urls', function () {
    $m3u = <<<'M3U'
    #EXTM3U x-tvg-url="https://epg.example/guide.xml"
    #EXTINF:-1 tvg-id="tvri.id" tvg-chno="101" tvg-logo="https://logo.example/tvri.png" group-title="Nasional",TVRI Nasional
    https://cdn.example/tvri/index.m3u8
    #EXTINF:-1 group-title="Berita;News",DW, English
    #EXTVLCOPT:http-user-agent=Foo
    https://cdn.example/dw.m3u8?token=abc
    #EXTINF:-1,Encoder Lobby
    udp://239.1.1.50:1234
    #EXTINF:-1,Broken entry
    not-a-url
    M3U;

    $e = (new M3uImporter)->parse($m3u);

    expect($e)->toHaveCount(3)
        ->and($e[0])->toMatchArray(['name' => 'TVRI Nasional', 'number' => 101, 'group' => 'Nasional', 'logo' => 'https://logo.example/tvri.png'])
        ->and($e[1])->toMatchArray(['name' => 'DW, English', 'number' => null, 'group' => 'Berita', 'url' => 'https://cdn.example/dw.m3u8?token=abc'])
        ->and($e[2]['url'])->toBe('udp://239.1.1.50:1234');
});

it('detects the source type from the url', function () {
    expect(M3uImporter::detectSourceType('udp://239.1.1.1:1234', 'provider'))->toBe(SourceType::LocalMulticast)
        ->and(M3uImporter::detectSourceType('https://x/live.mpd?t=1', 'local'))->toBe(SourceType::ProviderDash)
        ->and(M3uImporter::detectSourceType('https://x/a.m3u8', 'provider'))->toBe(SourceType::ProviderHls)
        ->and(M3uImporter::detectSourceType('http://10.0.0.5/a.m3u8', 'local'))->toBe(SourceType::LocalHls);
});

it('rejects non-stream urls', function () {
    expect(M3uImporter::isPlayableUrl('javascript:alert(1)'))->toBeFalse()
        ->and(M3uImporter::isPlayableUrl('file:///etc/passwd'))->toBeFalse()
        ->and(M3uImporter::isPlayableUrl('rtp://239.0.0.1:5000'))->toBeTrue();
});
