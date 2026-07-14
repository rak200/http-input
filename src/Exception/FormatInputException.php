<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The value does not match the expected format — raised by the `email` /
 * `url` / `pattern` verifiers.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class FormatInputException extends InvalidInputException {}
