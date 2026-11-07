<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * A buyer action the service refuses with a message that is safe to show
 * the buyer (not eligible, already reviewed, sold out…). Extends
 * InvalidArgumentException so every existing web catch keeps working; the
 * API maps ONLY this class to 422 validation_failed, never a framework IAE.
 */
class BuyerActionException extends InvalidArgumentException {}
