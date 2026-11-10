<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * SubOrderStatusService::transition() refused a move the FSM does not
 * allow. Extends InvalidArgumentException so callers that catch the old
 * type keep working; the API maps it to 409 invalid_transition.
 */
class InvalidTransitionException extends InvalidArgumentException {}
