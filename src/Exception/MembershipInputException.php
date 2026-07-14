<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The value is not a member of the allowed ad-hoc set — raised by the `in`
 * verifier. (Membership in an enum is the `enum` coercer's job, which raises
 * the base {@see InvalidInputException} on failure.).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MembershipInputException extends InvalidInputException {}
