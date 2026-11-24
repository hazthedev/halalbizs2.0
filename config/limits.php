<?php

/*
 * Per-user send limits on the web screens (SEC-38). The app API has its own
 * route throttles (AppServiceProvider: api-chat, ...).
 */
return [
    // Chat (buyer and seller inbox): per user, per conversation.
    'chat_messages_per_minute' => 10,

    // Help centre: new support tickets per user.
    'support_tickets_per_hour' => 5,
];
