<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Fixture;

use function strlen;
use function substr;

/**
 * A stream wrapper standing in for the `php` protocol so a test can feed
 * `php://input` a real request body (or refuse to open it). Register with
 * `stream_wrapper_unregister('php')` + `stream_wrapper_register('php', ...)`
 * and always `stream_wrapper_restore('php')` in a finally — the native
 * wrapper is global state.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class PhpStreamMock
{
    public static string $body = '';

    public static bool $failToOpen = false;

    /**
     * @var null|resource
     */
    public $context;

    private int $offset = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return !self::$failToOpen;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->offset, $count);
        $this->offset += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->offset >= strlen(self::$body);
    }

    /**
     * @return array<int|string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }
}
