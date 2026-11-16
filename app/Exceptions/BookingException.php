<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking or reservation was refused for a reason that is safe to show to the person making it.
 */
class BookingException extends RuntimeException {}
