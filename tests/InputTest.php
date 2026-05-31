<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Input;

final class InputTest extends TestCase {
    public function testStr(): void {
        $source = ['name' => 'Ada', 'age' => 42, 'tags' => ['a']];
        $this->assertSame('Ada', Input::str($source, 'name'));
        $this->assertSame('42', Input::str($source, 'age'));        // coerced
        $this->assertNull(Input::str($source, 'tags'));             // array → uncoercible
        $this->assertNull(Input::str($source, 'missing'));
        $this->assertSame('d', Input::str($source, 'missing', 'd'));
    }

    public function testInt(): void {
        $source = ['page' => '3', 'bad' => 'x', 'whole' => 7];
        $this->assertSame(3, Input::int($source, 'page'));
        $this->assertSame(7, Input::int($source, 'whole'));
        $this->assertNull(Input::int($source, 'bad'));
        $this->assertSame(1, Input::int($source, 'bad', 1));
        $this->assertNull(Input::int($source, 'missing'));
        $this->assertSame(1, Input::int($source, 'missing', 1));
    }

    public function testIntClampsToBounds(): void {
        $source = ['page' => '0', 'big' => '500'];
        $this->assertSame(1, Input::int($source, 'page', 1, min: 1));
        $this->assertSame(100, Input::int($source, 'big', 1, min: 1, max: 100));
        $this->assertSame(50, Input::int($source, 'mid', 50, min: 1, max: 100));   // default within range
    }

    public function testFloat(): void {
        $source = ['price' => '9.99', 'bad' => 'x'];
        $this->assertSame(9.99, Input::float($source, 'price'));
        $this->assertNull(Input::float($source, 'bad'));
        $this->assertSame(0.0, Input::float($source, 'bad', 0.0));
        $this->assertNull(Input::float($source, 'missing'));
    }

    public function testFloatClampsToBounds(): void {
        $source = ['v' => '12.5'];
        $this->assertSame(10.0, Input::float($source, 'v', null, min: 0.0, max: 10.0));
        $this->assertSame(2.0, Input::float($source, 'low', 2.0, min: 1.0, max: 5.0));
    }

    public function testBool(): void {
        $source = ['remember' => 'on', 'subscribe' => '0', 'weird' => 'maybe'];
        $this->assertTrue(Input::bool($source, 'remember'));
        $this->assertFalse(Input::bool($source, 'subscribe'));
        $this->assertNull(Input::bool($source, 'weird'));
        $this->assertFalse(Input::bool($source, 'weird', false));
        $this->assertNull(Input::bool($source, 'missing'));
        $this->assertTrue(Input::bool($source, 'missing', true));
    }

    public function testArray(): void {
        $source = ['tags' => ['a', 'b'], 'name' => 'Ada'];
        $this->assertSame(['a', 'b'], Input::array($source, 'tags'));
        $this->assertNull(Input::array($source, 'name'));            // scalar → not an array
        $this->assertNull(Input::array($source, 'missing'));
        $this->assertSame([], Input::array($source, 'missing', []));
    }

    public function testHas(): void {
        $source = ['present' => null, 'value' => 1];
        $this->assertTrue(Input::has($source, 'present'));           // present even when null
        $this->assertTrue(Input::has($source, 'value'));
        $this->assertFalse(Input::has($source, 'missing'));
    }

    public function testAll(): void {
        $source = ['a' => 1, 'b' => 2];
        $this->assertSame($source, Input::all($source));
    }

    public function testCoreReadsSuperglobalWhenPassedDirectly(): void {
        $get = ['page' => '5'];
        $this->assertSame(5, Input::int($get, 'page', 1));
    }

    #[BackupGlobals(true)]
    public function testGet(): void {
        $_GET = ['q' => 'hello'];
        $this->assertSame('hello', Input::get('q'));
        $this->assertNull(Input::get('missing'));
        $this->assertSame('d', Input::get('missing', 'd'));
    }

    #[BackupGlobals(true)]
    public function testPost(): void {
        $_POST = ['name' => 'Ada'];
        $this->assertSame('Ada', Input::post('name'));
        $this->assertSame('x', Input::post('missing', 'x'));
    }

    #[BackupGlobals(true)]
    public function testRequest(): void {
        $_REQUEST = ['id' => '7'];
        $this->assertSame('7', Input::request('id'));
    }

    #[BackupGlobals(true)]
    public function testCookie(): void {
        $_COOKIE = ['session' => 'abc'];
        $this->assertSame('abc', Input::cookie('session'));
    }

    #[BackupGlobals(true)]
    public function testServer(): void {
        $_SERVER['HTTP_X_TEST'] = 'yes';
        $this->assertSame('yes', Input::server('HTTP_X_TEST'));
    }

    #[BackupGlobals(true)]
    public function testEnv(): void {
        $_ENV = ['APP_ENV' => 'testing'];
        $this->assertSame('testing', Input::env('APP_ENV'));
        $this->assertSame('prod', Input::env('MISSING', 'prod'));
    }
}
