<?php

declare(strict_types=1);

namespace Amashukov\Rlp\Tests;

use Amashukov\Rlp\Rlp;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rlp::class)]
final class RlpTest extends TestCase
{
    public function testEncodeEmptyString(): void
    {
        self::assertSame("\x80", Rlp::encode(''));
    }

    public function testEncodeSingleByteBelowThreshold(): void
    {
        self::assertSame("\x00", Rlp::encode("\x00"));
        self::assertSame("\x7f", Rlp::encode("\x7f"));
    }

    public function testEncodeSingleByteAtThresholdGetsLengthPrefix(): void
    {
        self::assertSame("\x81\x80", Rlp::encode("\x80"));
    }

    public function testEncodeShortString(): void
    {
        self::assertSame("\x83\x64\x6f\x67", Rlp::encode('dog'));
    }

    public function testEncodeBoundary55ByteString(): void
    {
        $payload = str_repeat('a', 55);
        $encoded = Rlp::encode($payload);
        self::assertSame(chr(0x80 + 55) . $payload, $encoded);
    }

    public function testEncodeLongStringUsesLongFormPrefix(): void
    {
        $payload = str_repeat('a', 56);
        $encoded = Rlp::encode($payload);
        self::assertSame("\xb8\x38" . $payload, $encoded);
    }

    public function testEncodeVeryLongString1024Bytes(): void
    {
        $payload = str_repeat('a', 1024);
        $encoded = Rlp::encode($payload);
        self::assertSame("\xb9\x04\x00" . $payload, $encoded);
    }

    public function testEncodeEmptyList(): void
    {
        self::assertSame("\xc0", Rlp::encode([]));
    }

    public function testEncodeFlatListOfStrings(): void
    {
        self::assertSame("\xc8\x83cat\x83dog", Rlp::encode(['cat', 'dog']));
    }

    public function testEncodeNestedList(): void
    {
        self::assertSame("\xc7\xc0\xc1\xc0\xc3\xc0\xc1\xc0", Rlp::encode([[], [[]], [[], [[]]]]));
    }

    public function testEncodeIntegerZero(): void
    {
        self::assertSame("\x80", Rlp::encodeInt(0));
    }

    public function testEncodeIntegerOne(): void
    {
        self::assertSame("\x01", Rlp::encodeInt(1));
    }

    public function testEncodeInteger127StaysSingleByte(): void
    {
        self::assertSame("\x7f", Rlp::encodeInt(127));
    }

    public function testEncodeInteger128PromotesToTwoBytes(): void
    {
        self::assertSame("\x81\x80", Rlp::encodeInt(128));
    }

    public function testEncodeInteger1024(): void
    {
        self::assertSame("\x82\x04\x00", Rlp::encodeInt(1024));
    }

    public function testEncodeIntegerAsDecimalStringForLargeValues(): void
    {
        $encoded = Rlp::encodeInt('1000000000000000000');
        self::assertSame("\x88\x0d\xe0\xb6\xb3\xa7\x64\x00\x00", $encoded);
    }

    public function testEncodeIntRejectsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::encodeInt(-1);
    }

    public function testEncodeIntRejectsNonNumericString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::encodeInt('not-a-number');
    }

    public function testEncodeRejectsNonStringNonArrayListItem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::encode($this->mixedItemList());
    }

    /**
     * @return array<int, mixed>
     */
    private function mixedItemList(): array
    {
        return [42];
    }

    /**
     * @return array<string, array{0: string|array<int, mixed>}>
     */
    public static function roundtripProvider(): array
    {
        return [
            'empty string'                 => [''],
            'single byte below threshold'  => ["\x42"],
            'short text'                   => ['hello world'],
            'exactly 55 bytes'             => [str_repeat('x', 55)],
            'exactly 56 bytes'             => [str_repeat('x', 56)],
            '1024 bytes'                   => [str_repeat('Z', 1024)],
            'empty list'                   => [[]],
            'flat list'                    => [['cat', 'dog', 'mouse']],
            'nested list'                  => [[[], [[]], [[], [[]]]]],
            'mixed nested list'            => [['a', ['b', ['c', ['d']]], 'e']],
            'list of large strings'       => [[str_repeat('A', 60), str_repeat('B', 200)]],
        ];
    }

    /**
     * @param string|array<int, mixed> $value
     */
    #[DataProvider('roundtripProvider')]
    public function testEncodeDecodeRoundtrip(string|array $value): void
    {
        $encoded = Rlp::encode($value);
        $decoded = Rlp::decode($encoded);
        self::assertSame($value, $decoded);
    }

    public function testDecodeKnownVectorEmptyString(): void
    {
        self::assertSame('', Rlp::decode("\x80"));
    }

    public function testDecodeKnownVectorSingleByteIsIdentity(): void
    {
        self::assertSame("\x00", Rlp::decode("\x00"));
        self::assertSame("\x7f", Rlp::decode("\x7f"));
    }

    public function testDecodeKnownVectorShortString(): void
    {
        self::assertSame('dog', Rlp::decode("\x83\x64\x6f\x67"));
    }

    public function testDecodeKnownVectorFlatList(): void
    {
        self::assertSame(['cat', 'dog'], Rlp::decode("\xc8\x83cat\x83dog"));
    }

    public function testDecodeRejectsTrailingBytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\x80\x00");
    }

    public function testDecodeRejectsEmptyInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode('');
    }

    public function testDecodeRejectsTruncatedShortString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\x83\x64\x6f");
    }

    public function testDecodeRejectsTruncatedLongString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\xb8\x38" . str_repeat('a', 10));
    }

    public function testDecodeRejectsNonCanonicalSingleByteLongForm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\x81\x00");
    }

    public function testDecodeRejectsNonCanonicalShortLengthInLongForm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\xb8\x37" . str_repeat('a', 55));
    }

    public function testDecodeRejectsNonCanonicalShortListLengthInLongForm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\xf8\x37" . str_repeat("\x80", 55));
    }

    public function testDecodeRejectsLeadingZeroInLongLengthEncoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decode("\xb9\x00\x38" . str_repeat('a', 56));
    }

    public function testDecodeStreamReportsConsumedBytes(): void
    {
        $bytes = "\x83\x64\x6f\x67\xc1\x80";
        [$first, $consumed] = Rlp::decodeStream($bytes, 0);
        self::assertSame('dog', $first);
        self::assertSame(4, $consumed);

        [$second, $consumed2] = Rlp::decodeStream($bytes, $consumed);
        self::assertSame([''], $second);
        self::assertSame(2, $consumed2);
    }

    public function testDecodeStreamRejectsOffsetBeyondInput(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rlp::decodeStream("\x80", 5);
    }
}
