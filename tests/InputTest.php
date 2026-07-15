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
use Rak200\HttpInput\Tests\Fixture\PhpStreamMock;

use function restore_error_handler;
use function set_error_handler;
use function stream_wrapper_register;
use function stream_wrapper_restore;
use function stream_wrapper_unregister;

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

    public function testJsonReadsAndValidatesTheRequestBody(): void
    {
        PhpStreamMock::$body = '{"name": "Ada", "age": 36}';
        PhpStreamMock::$failToOpen = false;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', PhpStreamMock::class);

        try {
            $result = Input::json(Schema::object(['name' => Rule::str(), 'age' => Rule::int()]));
        } finally {
            stream_wrapper_restore('php');
        }

        $this->assertFalse($result->fails());
        $this->assertSame(['name' => 'Ada', 'age' => 36], $result->values());
    }

    public function testJsonTreatsAnUnreadableBodyAsMalformed(): void
    {
        // file_get_contents() returning false is mapped to the empty body,
        // so the failure mode stays JsonException — never a TypeError.
        PhpStreamMock::$failToOpen = true;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', PhpStreamMock::class);
        set_error_handler(static fn (): bool => true);   // swallow file_get_contents' E_WARNING

        $this->expectException(JsonException::class);

        try {
            Input::json(Schema::object(['name' => Rule::str()]));
        } finally {
            restore_error_handler();
            stream_wrapper_restore('php');
            PhpStreamMock::$failToOpen = false;
        }
    }
}
