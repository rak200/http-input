<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The key is absent from the source.
 *
 * Raised by the `value()` terminal on an absent key and by the `required()`
 * verifier in collect mode. Distinct from {@see InvalidInputException} on
 * purpose: "you didn't send `page`" and "you sent `page=abc`" are different
 * failures and callers may branch on them.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MissingInputException extends InputException {}
