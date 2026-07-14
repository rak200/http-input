<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use JsonException;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Accessor;
use Rak200\HttpInput\Input;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Schema;

/**
 * @internal
 *
 * @coversNothing
 */
final class InputTest extends TestCase
{
    #[BackupGlobals(true)]
    public function testGetBindsTheGetSuperglobal(): void
    {
        $_GET = ['page' => '5', 'q' => 'hello'];

        $this->assertInstanceOf(Accessor::class, Input::get('page'));
        $this->assertSame(5, Input::get('page')->int()->min(1)->value());
        $this->assertSame('hello', Input::get('q')->str()->orNull());
        $this->assertSame(1, Input::get('missing')->int()->orElse(1));
    }

    #[BackupGlobals(true)]
    public function testPostBindsThePostSuperglobal(): void
    {
        $_POST = ['name' => 'Ada'];

        $this->assertSame('Ada', Input::post('name')->str()->required()->value());
    }

    #[BackupGlobals(true)]
    public function testCookieBindsTheCookieSuperglobal(): void
    {
        $_COOKIE = ['session' => 'abc'];

        $this->assertSame('abc', Input::cookie('session')->str()->value());
    }

    #[BackupGlobals(true)]
    public function testServerBindsTheServerSuperglobal(): void
    {
        $_SERVER['HTTP_X_TEST'] = 'yes';

        $this->assertSame('yes', Input::server('HTTP_X_TEST')->str()->value());
    }

    #[BackupGlobals(true)]
    public function testEnvBindsTheEnvSuperglobal(): void
    {
        $_ENV = ['APP_ENV' => 'testing'];

        $this->assertSame('testing', Input::env('APP_ENV')->str()->value());
        $this->assertSame('prod', Input::env('MISSING')->str()->orElse('prod'));
    }

    #[BackupGlobals(true)]
    public function testRequestBindsTheRequestSuperglobal(): void
    {
        $_REQUEST = ['id' => '7'];

        $this->assertSame(7, Input::request('id')->int()->value());
    }

    #[BackupGlobals(true)]
    public function testShortcutsReturnAccessorsNotPreTerminatedValues(): void
    {
        $_GET = ['remember' => 'on'];

        // Uniform with the chain: the caller picks the coercer and terminal.
        $this->assertTrue(Input::get('remember')->bool()->value());
        $this->assertFalse(Input::get('absent_checkbox')->bool()->value());
    }

    public function testJsonThrowsJsonExceptionOnAMalformedBody(): void
    {
        // Under CLI php://input is empty — not valid JSON, so the malformed
        // body surfaces as a JsonException, distinct from schema errors.
        $this->expectException(JsonException::class);

        Input::json(Schema::object(['name' => Rule::str()]));
    }
}
