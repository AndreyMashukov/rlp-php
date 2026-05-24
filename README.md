# amashukov/rlp-php

Pure-PHP RLP (Recursive Length Prefix) encoder and decoder — Ethereum's canonical serialization for transactions, blocks and the state trie.

[![CI](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/rlp-php/ci.yml?branch=main&label=CI)](https://github.com/AndreyMashukov/rlp-php/actions)
[![PHPStan L9](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/rlp-php/stan.yml?branch=main&label=PHPStan%20L9)](https://github.com/AndreyMashukov/rlp-php/actions)
[![Latest Version](https://img.shields.io/packagist/v/amashukov/rlp-php)](https://packagist.org/packages/amashukov/rlp-php)
[![Downloads](https://img.shields.io/packagist/dt/amashukov/rlp-php)](https://packagist.org/packages/amashukov/rlp-php)
[![PHP](https://img.shields.io/packagist/dependency-v/amashukov/rlp-php/php)](https://packagist.org/packages/amashukov/rlp-php)
[![License](https://img.shields.io/packagist/l/amashukov/rlp-php)](LICENSE)
[![Stars](https://img.shields.io/github/stars/AndreyMashukov/rlp-php?style=social)](https://github.com/AndreyMashukov/rlp-php)

`amashukov/rlp-php` is a pure-PHP implementation of RLP (Recursive Length Prefix), the canonical serialization format used throughout Ethereum and the EVM for transactions, blocks and the Merkle-Patricia state trie. It encodes and decodes nested byte-string structures and minimal big-endian integers, and enforces strict RLP canonicality on decode to defend against malleable encodings.

The package is a leaf primitive — zero composer dependencies, just `ext-gmp` for big-integer encoding helpers.

## Features

- **Encode & decode** arbitrarily nested lists of byte-strings.
- **Integer encoding** in Ethereum's minimal big-endian convention, including big integers via decimal strings.
- **Strict canonical decode** — rejects non-canonical length prefixes, leading-zero lengths, trailing bytes and truncated input.
- **Stream decoding** for parsing concatenated RLP items (e.g. a sequence of transactions).
- **Zero composer dependencies** — `ext-gmp` only.
- PHPStan level 9 clean, `@PER-CS` formatted, CI-tested.

## Installation

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

## Related packages

Part of a modular pure-PHP blockchain toolkit:

| Package | Purpose |
|---|---|
| [amashukov/keccak-php](https://github.com/AndreyMashukov/keccak-php) | Keccak-256 / SHA-3 / SHAKE hashing |
| [amashukov/secp256k1-php](https://github.com/AndreyMashukov/secp256k1-php) | secp256k1 ECDSA sign / verify / recover |
| [amashukov/rlp-php](https://github.com/AndreyMashukov/rlp-php) | Ethereum RLP encode / decode |
| [amashukov/ton-cell-php](https://github.com/AndreyMashukov/ton-cell-php) | TON TLB Cell / Builder / Slice / BOC |
| [amashukov/eip1559-tx-signer-php](https://github.com/AndreyMashukov/eip1559-tx-signer-php) | EIP-1559 transaction signer |
| [amashukov/abi-encoder-php](https://github.com/AndreyMashukov/abi-encoder-php) | Ethereum ABI encoder |
| [amashukov/eth-rpc-client-php](https://github.com/AndreyMashukov/eth-rpc-client-php) | Ethereum JSON-RPC client |
| [amashukov/eth-php](https://github.com/AndreyMashukov/eth-php) | EVM umbrella package |

## Quality

- PHPStan level 9.
- php-cs-fixer with the `@PER-CS` ruleset.
- GitHub Actions CI on every push.
- Test vectors validated against the Ethereum RLP / Yellow Paper reference outputs.

## Reference

- Ethereum RLP specification: <https://ethereum.org/en/developers/docs/data-structures-and-encoding/rlp/>
- Yellow Paper §B (Recursive Length Prefix): <https://ethereum.github.io/yellowpaper/paper.pdf>

## License

MIT License.
