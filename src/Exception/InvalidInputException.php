<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The value is present but failed the chain — either the opening coercer
 * (the value cannot be represented as the declared type) or one of the
 * verifiers. Per-constraint subtypes (e.g. {@see OutOfRangeInputException})
 * extend this, so catching it still catches every present-but-invalid
 * failure.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class InvalidInputException extends InputException {}
