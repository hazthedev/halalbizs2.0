<?php

namespace App\Exceptions;

use Exception;

/**
 * Carries a human-readable reason safe to show the buyer
 * ("Blue / XL just sold out — 0 left").
 */
class CheckoutException extends Exception {}
