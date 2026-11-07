<?php

namespace App\Exceptions;

use Exception;

/**
 * ProductPublishPolicy said no. The message is the policy's seller-facing
 * reason, so every surface (API 409 `halal_blocked`, Livewire toast) shows
 * the same words.
 */
class ProductPublishBlockedException extends Exception {}
