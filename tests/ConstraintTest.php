<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Constraint;
use Rak200\HttpInput\Violation;

use function is_int;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConstraintTest extends TestCase
{
    public function testPassingValueYieldsNull(): void
    {
        $this->assertNull(self::even()->check(4));
    }

    public function testFailingValueYieldsTheViolation(): void
    {
        $violation = self::even()->check(3);

        $this->assertInstanceOf(Violation::class, $violation);
        $this->assertSame('must be even', $violation->message);
    }

    public function testConstraintIsFieldAgnosticAndReusable(): void
    {
        $constraint = self::even();

        $this->assertNull($constraint->check(0));
        $this->assertNotNull($constraint->check('4')); // value, not text: strings fail
        $this->assertNotNull($constraint->check(null));
    }

    private static function even(): Constraint
    {
        return new class implements Constraint {
            public function check(mixed $value): ?Violation
            {
                return is_int($value) && $value % 2 === 0 ? null : new Violation('must be even');
            }
        };
    }
}
