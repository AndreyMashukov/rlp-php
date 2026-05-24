# rlp-php

RLP (Recursive Length Prefix) encoder and decoder in pure PHP — the canonical serialization format used throughout Ethereum for transactions, blocks, and the state trie.

The package is a leaf primitive — zero composer dependencies, just `ext-gmp` for big-integer encoding helpers.

## Install

```bash
composer require amashukov/rlp-php
```

## Usage

### Encode

```php
use Amashukov\Rlp\Rlp;

// Empty string.
Rlp::encode('');                              // 0x80

// Single byte < 0x80 is its own encoding.
Rlp::encode("\x7f");                          // 0x7f

// Short string (≤ 55 bytes).
Rlp::encode('dog');                           // 0x83 'd' 'o' 'g'

// Long string (> 55 bytes).
Rlp::encode(str_repeat('a', 56));             // 0xb8 0x38 'a' × 56

// Empty list.
Rlp::encode([]);                              // 0xc0

// Flat list of byte-strings.
Rlp::encode(['cat', 'dog']);                  // 0xc8 0x83 'cat' 0x83 'dog'

// Nested list.
Rlp::encode([[], [[]], [[], [[]]]]);          // 0xc7 0xc0 0xc1 0xc0 0xc3 0xc0 0xc1 0xc0
```

### Encode integers (Ethereum convention: minimal big-endian)

```php
Rlp::encodeInt(0);                            // 0x80  (empty bytes)
Rlp::encodeInt(1);                            // 0x01
Rlp::encodeInt(127);                          // 0x7f
Rlp::encodeInt(128);                          // 0x81 0x80
Rlp::encodeInt(1024);                         // 0x82 0x04 0x00
Rlp::encodeInt('1000000000000000000');        // big-int via decimal string (1 ETH in wei)
```

### Decode

```php
Rlp::decode("\x80");                          // ''
Rlp::decode("\x83dog");                       // 'dog'
Rlp::decode("\xc8\x83cat\x83dog");            // ['cat', 'dog']
Rlp::decode("\xc7\xc0\xc1\xc0\xc3\xc0\xc1\xc0"); // [[], [[]], [[], [[]]]]
```

Leaves are always returned as raw byte strings; integers are the caller's interpretation step.

### Stream decoding

For parsing concatenated RLP items (e.g. a stream of transactions), use `decodeStream`:

```php
$stream    = Rlp::encode('first') . Rlp::encode('second');
[$first,  $consumed]  = Rlp::decodeStream($stream, 0);
[$second, $consumed2] = Rlp::decodeStream($stream, $consumed);
// $first = 'first', $second = 'second'
```

## Canonical decoding

`decode()` enforces RLP canonicality. The following inputs are rejected with `InvalidArgumentException`:

- A single byte < 0x80 wrapped in a long-form prefix (`0x81 0x00`).
- A short string encoded as a long string (length < 56 with `0xb8…` prefix).
- A short list encoded as a long list (payload length < 56 with `0xf8…` prefix).
- A long-form length with a leading zero byte (`0xb9 0x00 0x38 …`).
- Trailing bytes after the root item.
- Truncated input where the declared length exceeds the available bytes.

These checks defend downstream consumers against malleable transaction encodings and trie tampering.

## Requirements

- PHP 8.3+
- `ext-gmp`

No composer dependencies.

## Reference

- Ethereum RLP specification: <https://ethereum.org/en/developers/docs/data-structures-and-encoding/rlp/>
- Yellow Paper §B (Recursive Length Prefix): <https://ethereum.github.io/yellowpaper/paper.pdf>

## License

MIT License.
