<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// ── Per-user private channel ────────────────────────────────────────────────
// Both customers and providers subscribe to their own user.{id} channel.
// The auth callback ensures a user can ONLY subscribe to their own user channel.
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── Global service-requests admin channel ───────────────────────────────────
// Only users with the 'admin' role may subscribe to the full feed.
Broadcast::channel('service-requests', function ($user) {
    return $user->hasRole('admin');
});
