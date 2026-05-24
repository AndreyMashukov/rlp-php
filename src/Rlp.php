<?php

declare(strict_types=1);

namespace Amashukov\Rlp;

use InvalidArgumentException;

final class Rlp
{
    private function __construct() {}

    /**
     * @param string|array<mixed> $input
     */
    public static function encode(string|array $input): string
    {
        if (is_string($input)) {
            return self::encodeBytes($input);
        }

        $payload = '';
        foreach ($input as $item) {
            if (!is_string($item) && !is_array($item)) {
                throw new InvalidArgumentException('RLP list items must be strings or arrays; got ' . get_debug_type($item));
            }
            $payload .= self::encode($item);
        }

        return self::lengthPrefix($payload, 0xc0) . $payload;
    }

    public static function encodeInt(int|string $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException('RLP integers are non-negative.');
            }
            $hex = dechex($value);
        } else {
            if ('' === $value || 1 !== preg_match('/^\d+$/', $value)) {
                throw new InvalidArgumentException('encodeInt expects a non-negative decimal-string.');
            }
            $hex = gmp_strval(gmp_init($value, 10), 16);
        }

        $hex = ltrim($hex, '0');
        if ('' === $hex) {
            return self::encode('');
        }
        if (1 === strlen($hex) % 2) {
            $hex = '0' . $hex;
        }
        $bin = hex2bin($hex);
        if (false === $bin) {
            throw new InvalidArgumentException('encodeInt: hex conversion failed.');
        }

        return self::encode($bin);
    }

    /**
     * @return string|array<int, mixed>
     */
    public static function decode(string $bytes): string|array
    {
        if ('' === $bytes) {
            throw new InvalidArgumentException('Cannot decode empty input.');
        }

        [$value, $consumed] = self::decodeItem($bytes, 0);
        if ($consumed !== strlen($bytes)) {
            throw new InvalidArgumentException(sprintf('Trailing bytes after RLP root item: consumed %d of %d.', $consumed, strlen($bytes)));
        }

        return $value;
    }

    /**
     * @return array{0: string|array<int, mixed>, 1: int} `[value, bytes consumed]`
     */
    public static function decodeStream(string $bytes, int $offset = 0): array
    {
        return self::decodeItem($bytes, $offset);
    }

    private static function encodeBytes(string $bytes): string
    {
        if (1 === strlen($bytes) && ord($bytes) < 0x80) {
            return $bytes;
        }

        return self::lengthPrefix($bytes, 0x80) . $bytes;
    }

    private static function lengthPrefix(string $payload, int $offset): string
    {
        $length = strlen($payload);
        if ($length <= 55) {
            return chr(($offset + $length) & 0xFF);
        }

        $lenBytes = self::encodeLength($length);

        return chr(($offset + 55 + strlen($lenBytes)) & 0xFF) . $lenBytes;
    }

    private static function encodeLength(int $length): string
    {
        $hex = dechex($length);
        if (1 === strlen($hex) % 2) {
            $hex = '0' . $hex;
        }
        $bin = hex2bin($hex);
        if (false === $bin) {
            throw new InvalidArgumentException('encodeLength: hex conversion failed.');
        }

        return $bin;
    }

    /**
     * @return array{0: string|array<int, mixed>, 1: int}
     */
    private static function decodeItem(string $bytes, int $offset): array
    {
        $totalLen = strlen($bytes);
        if ($offset >= $totalLen) {
            throw new InvalidArgumentException('Offset exceeds input length.');
        }

        $prefix = ord($bytes[$offset]);

        if ($prefix < 0x80) {
            return [$bytes[$offset], 1];
        }

        if ($prefix <= 0xb7) {
            $payloadLen = $prefix - 0x80;
            self::assertCapacity($offset + 1 + $payloadLen, $totalLen);
            $payload = substr($bytes, $offset + 1, $payloadLen);
            if (1 === $payloadLen && ord($payload) < 0x80) {
                throw new InvalidArgumentException('Non-canonical RLP: single byte < 0x80 must be encoded as itself.');
            }

            return [$payload, 1 + $payloadLen];
        }

        if ($prefix <= 0xbf) {
            $lenOfLen = $prefix - 0xb7;
            self::assertCapacity($offset + 1 + $lenOfLen, $totalLen);
            $payloadLen = self::readLength($bytes, $offset + 1, $lenOfLen);
            if ($payloadLen < 56) {
                throw new InvalidArgumentException('Non-canonical RLP: length < 56 must use short-string form.');
            }
            self::assertCapacity($offset + 1 + $lenOfLen + $payloadLen, $totalLen);

            return [substr($bytes, $offset + 1 + $lenOfLen, $payloadLen), 1 + $lenOfLen + $payloadLen];
        }

        if ($prefix <= 0xf7) {
            $payloadLen = $prefix - 0xc0;
            self::assertCapacity($offset + 1 + $payloadLen, $totalLen);

            return [self::decodeList(substr($bytes, $offset + 1, $payloadLen)), 1 + $payloadLen];
        }

        $lenOfLen = $prefix - 0xf7;
        self::assertCapacity($offset + 1 + $lenOfLen, $totalLen);
        $payloadLen = self::readLength($bytes, $offset + 1, $lenOfLen);
        if ($payloadLen < 56) {
            throw new InvalidArgumentException('Non-canonical RLP: list length < 56 must use short-list form.');
        }
        self::assertCapacity($offset + 1 + $lenOfLen + $payloadLen, $totalLen);

        return [self::decodeList(substr($bytes, $offset + 1 + $lenOfLen, $payloadLen)), 1 + $lenOfLen + $payloadLen];
    }

    /**
     * @return array<int, mixed>
     */
    private static function decodeList(string $payload): array
    {
        $items     = [];
        $offset    = 0;
        $totalLen  = strlen($payload);
        while ($offset < $totalLen) {
            [$value, $consumed] = self::decodeItem($payload, $offset);
            $items[] = $value;
            $offset += $consumed;
        }

        return $items;
    }

    private static function readLength(string $bytes, int $offset, int $lenOfLen): int
    {
        if ($lenOfLen < 1 || $lenOfLen > 8) {
            throw new InvalidArgumentException('RLP length-of-length out of supported range [1, 8].');
        }
        if (ord($bytes[$offset]) === 0) {
            throw new InvalidArgumentException('Non-canonical RLP: length encoding has leading zero byte.');
        }
        $hex = bin2hex(substr($bytes, $offset, $lenOfLen));
        $value = (int) hexdec($hex);
        if ($value < 0) {
            throw new InvalidArgumentException('RLP length exceeds PHP_INT_MAX.');
        }

        return $value;
    }

    private static function assertCapacity(int $needEnd, int $haveEnd): void
    {
        if ($needEnd > $haveEnd) {
            throw new InvalidArgumentException(sprintf('Truncated RLP input: need %d bytes, have %d.', $needEnd, $haveEnd));
        }
    }
}
