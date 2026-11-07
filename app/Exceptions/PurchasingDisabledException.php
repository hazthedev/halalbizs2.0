<?php

namespace App\Exceptions;

/**
 * The marketplace is in listing-only mode (GeneralSettings::purchasing_enabled
 * off). A CheckoutException subclass so every existing web catch keeps
 * working; the API maps it to its own 409 code `purchasing_disabled`.
 */
class PurchasingDisabledException extends CheckoutException {}
