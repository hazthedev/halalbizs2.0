<?php

use App\Enums\LiveSessionStatus;
use App\Models\LiveSession;
use App\Models\Store;
use App\Models\User;

// UrlHostTest covers the matching rules; these prove the live room actually
// USES them — the string reaches an iframe src, so a green unit test on a
// helper nobody called would be worth nothing.

function liveRoomWith(?string $videoUrl): LiveSession
{
    $owner = User::factory()->create();
    $store = Store::factory()->approved()->create(['user_id' => $owner->id]);

    return LiveSession::create([
        'store_id' => $store->id,
        'title' => 'Ramadan special',
        'slug' => 'ramadan-special-'.uniqid(),
        'status' => LiveSessionStatus::Live,
        'video_url' => $videoUrl,
    ]);
}

it('embeds a genuine YouTube watch url by rebuilding it from the video id', function () {
    $session = liveRoomWith('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    $this->get(route('live.room', $session->slug))
        ->assertOk()
        ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false);
});

it('refuses to embed a hostile url that merely mentions an allow-listed host', function () {
    // The exact shape the old str_contains gate accepted.
    $session = liveRoomWith('https://evil.example/x#youtube.com/embed/abc');

    $this->get(route('live.room', $session->slug))
        ->assertOk()
        ->assertDontSee('evil.example', false)
        ->assertDontSee('<iframe', false);
});

it('refuses a watch url on a lookalike host instead of borrowing its video id', function () {
    // Contains a valid-looking watch id, but the host is not YouTube — the
    // rebuild path would otherwise render someone else's video from any domain.
    $session = liveRoomWith('https://notyoutube.com/watch?v=dQw4w9WgXcQ');

    $this->get(route('live.room', $session->slug))
        ->assertOk()
        ->assertDontSee('<iframe', false);
});

it('refuses a userinfo-spoofed embed url', function () {
    $session = liveRoomWith('https://youtube.com@evil.example/embed/abc');

    $this->get(route('live.room', $session->slug))
        ->assertOk()
        ->assertDontSee('evil.example', false)
        ->assertDontSee('<iframe', false);
});

it('still embeds a real facebook plugin url', function () {
    $session = liveRoomWith('https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fexample.com');

    $this->get(route('live.room', $session->slug))
        ->assertOk()
        ->assertSee('facebook.com/plugins/video.php', false);
});

it('renders no iframe when the seller has set no video at all', function () {
    $session = liveRoomWith(null);

    $this->get(route('live.room', $session->slug))
        ->assertOk()
        ->assertDontSee('<iframe', false);
});
