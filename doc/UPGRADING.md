# Upgrading Horde Mail

This document lists API changes between releases. For the lib/ to src/
migration guide see [Migrating to the PSR-4 API](#migrating-to-the-psr-4-api).

---

## Upgrading to 3.0.0

Horde/Mail 3.0.0 is the last version to feature a PSR-0 class layout along with the new PSR-4 library.
This is an opportunity for integrators transitioning at their own pace. The traditional class layout is going to be deprecated and removed at some point.

### Migrating to the PSR-4 API

The `src/` tree (`Horde\Mail\*`) is a ground-up refresh for PHP 8.1+. It is
**not** a drop-in replacement for the `lib/` classes. Adopting it requires
both mechanical and conceptual changes.

The `lib/` tree remains functional and is not deprecated yet. 
Both trees can coexist in the same ecosystem for a transitional phase.

### Key conceptual changes

#### Immutable value objects

`lib/` addresses are mutable. You set `->mailbox`, `->host`, `->personal`
after construction and change the existing instance. The `src/` addresses are `final readonly`:

```php
// lib/ — mutable
$addr = new Horde_Mail_Rfc822_Address();
$addr->mailbox = 'user';
$addr->host = 'example.com';

// src/ — immutable, set at construction
$addr = new Horde\Mail\Rfc822\Address('user', 'example.com');
```

#### Parser is a separate object

`lib/` uses a single `Horde_Mail_Rfc822` class for both parsing and static
helpers. `src/` separates these concerns:

```php
// lib/
$rfc822 = new Horde_Mail_Rfc822();
$list = $rfc822->parseAddressList($input, ['validate' => true]);

// src/
$config = new Rfc822ParserConfig(validation: ValidationMode::Strict);
$parser = new Rfc822Parser($config);
$list = $parser->parseAddressList($input);
```

Configuration is a readonly DTO (`Rfc822ParserConfig`) instead of an options
array. Validation mode is an enum (`ValidationMode`) instead of mixed
`true`/`'eai'`/`false`.

#### Typed transport interface

`lib/` transport is an abstract class with `send($recipients, $headers, $body)`
where all three parameters are loosely typed (string/array mix). `src/` uses
an interface with full type declarations:

```php
// lib/
$transport->send('user@example.com', $headers, $bodyString);

// src/
$recipients = AddressList::from(new Address('user', 'example.com'));
$transport->send($recipients, $headers, $bodyOrStream);
```

- Recipients are `AddressList`, not strings or arrays.
- Body accepts `string|StreamInterface` (from `horde/stream`), never raw
  `resource`.
- Headers remain `array<string, string|string[]>` — the `_raw` key convention
  is preserved.
- `send()` returns `void` and throws `TransportException` on failure (lib/
  `prepareHeaders()` returned `false`).

Note: The revised design is open to custom and third party transports. In fact, the SMTP and LMTP transports are marked for potentially migrating to the SMTP package.

#### Configuration DTOs replace constructor arrays

```php
// lib/
$transport = new Horde_Mail_Transport_Sendmail([
    'sendmail_path' => '/usr/sbin/sendmail',
    'sendmail_args' => '-oi',
]);

// src/
$config = new SendmailConfig(
    sendmailPath: '/usr/sbin/sendmail',
    sendmailArgs: '-oi',
);
$transport = new SendmailTransport($config);
```

#### No base class, no ArrayAccess

`lib/` objects extend `Horde_Mail_Rfc822_Object` and implement `ArrayAccess`
for backward compatibility with pre-1.1 array returns. `src/` has no base
class and no array access. Use the typed properties directly.

#### PSR-14 events replace implicit callbacks

The parser emits `AddressParsed`, `GroupParsed` and `ParseError` events
via a PSR-14 `EventDispatcherInterface`. Pass a dispatcher through
`Rfc822ParserConfig` to observe parsing.

### Class mapping

| lib/ (PSR-0) | src/ (PSR-4) | Notes |
|---|---|---|
| `Horde_Mail_Exception` | `Horde\Mail\MailException` | |
| `Horde_Mail_Rfc822` | `Horde\Mail\Rfc822\Rfc822Parser` | Parser only. Static helpers moved to `Address`/`Rfc2047` |
| *(options array)* | `Horde\Mail\Rfc822\Rfc822ParserConfig` | Typed config DTO |
| *(bool/string)* | `Horde\Mail\Rfc822\ValidationMode` | Enum: `Lenient`, `Strict`, `Eai` |
| `Horde_Mail_Rfc822_Address` | `Horde\Mail\Rfc822\Address` | `final readonly` |
| `Horde_Mail_Rfc822_Group` | `Horde\Mail\Rfc822\Group` | `final readonly` |
| `Horde_Mail_Rfc822_List` | `Horde\Mail\Rfc822\AddressList` | No `ArrayAccess` - use `first()`, `addresses()`, iteration |
| `Horde_Mail_Rfc822_GroupList` | *(removed)* | Groups live in `AddressList` directly |
| `Horde_Mail_Rfc822_Object` | *(removed)* | No base class needed |
| `Horde_Mail_Rfc822_Identification` | `Horde\Mail\Rfc822\MessageIdParser` | Parses Message-ID / References / In-Reply-To |
| `Horde_Mail_Translation` | *(removed)* | Plain English exceptions. No translation dependency |
| `Horde_Mail_Transport` | `Horde\Mail\Transport\Transport` | Interface, not abstract class |
| `Horde_Mail_Transport_Mock` | `Horde\Mail\Transport\MockTransport` | Returns `SentMessage` DTOs |
| `Horde_Mail_Transport_Null` | `Horde\Mail\Transport\NullTransport` | |
| `Horde_Mail_Transport_Mail` | `Horde\Mail\Transport\PhpMailTransport` | Config via `PhpMailConfig` |
| `Horde_Mail_Transport_Sendmail` | `Horde\Mail\Transport\SendmailTransport` | Config via `SendmailConfig` |
| `Horde_Mail_Transport_Smtphorde` | `Horde\Mail\Transport\SmtpTransport` | Requires `horde/smtp` |
| `Horde_Mail_Transport_Lmtphorde` | `Horde\Mail\Transport\LmtpTransport` | Requires `horde/smtp` |
| `Horde_Mail_Mbox_Parse` | `Horde\Mail\Mbox\MboxParser` | `Countable`+`IteratorAggregate`, no `ArrayAccess` |
| *(array return)* | `Horde\Mail\Mbox\MboxMessage` | `final readonly` DTO with `StreamInterface` data |

### Migration checklist

1. Require PHP 8.1+ and add `horde/stream` to your dependencies.
2. Replace `Horde_Mail_Rfc822` instantiation with `Rfc822Parser` + config.
3. Replace mutable address construction with `new Address(mailbox, host, ...)`.
4. Replace `parseAddressList()` option arrays with `Rfc822ParserConfig`.
5. Replace `$list[0]` array access with `$list->first()` or iteration.
6. Replace string/array recipients in `send()` with `AddressList::from(...)`.
7. Replace transport constructor arrays with config DTOs.
8. Replace `Horde_Mail_Exception` catches with `MailException` /
   `TransportException` / `ParseException`.
9. Replace `$transport->sentMessages[0]['body']` with
   `$transport->sentMessages()[0]->body`.
10. Replace `Horde_Mail_Mbox_Parse` array access (`$parse[0]['data']`) with
    `$parser->get(0)->data`.


## Upgrading to 2.5

- `Horde_Mail_Rfc822`
  - `encode()` — added the `comment` option to the `type` parameter.
- `Horde_Mail_Rfc822_Object`
  - `writeAddress()` — added the `comment` option.

## Upgrading to 2.5 (second batch)

- `Horde_Mail_Rfc822`
  - `ATEXT` constant is deprecated.
  - `parseAddressList()` — added the `eai` option to the `validate` parameter.
- `Horde_Mail_Rfc822_Address` — added `eai` property.
- `Horde_Mail_Rfc822_List` — added `first()` method.
- `Horde_Mail_Mbox_Parse` — new class for mbox format parsing.
- `Horde_Mail_Transport` — added `eai` property.
- `Horde_Mail_Transport_Smtp`, `Horde_Mail_Transport_Smtpmx` — deprecated.
  Use `Horde_Mail_Transport_Hordesmtp` instead.

## Upgrading to 2.4

- `Horde_Mail_Rfc822_Object`
  - `writeAddress()` — added the `noquote` option.

## Upgrading to 2.3

- `Horde_Mail_Transport_Lmtphorde` — new driver.

## Upgrading to 2.2

- `Horde_Mail_Rfc822_Identification` — new class.

## Upgrading to 2.1

- `Horde_Mail_Rfc822_Address` — added `bare_address_idn` property.
- `Horde_Mail_Rfc822_List` — added `bare_addresses_idn` property.
- `Horde_Mail_Transport_Smtphorde` — new driver.

## Upgrading to 2.0

- `Horde_Mail` — removed (`factory()` is gone). Instantiate transport
  drivers directly.
- `Horde_Mail_Rfc822`
  - Removed `num_groups` property and `validateMailbox()`.
  - `parseAddressList()` returns `Horde_Mail_Rfc822_List`.
  - Added `group` parameter and removed `nest_groups`.
  - No longer validates by default.
- `Horde_Mail_Rfc822_Address`
  - No longer accessible as an array.
  - Removed `adl`, `route`, `personal_decoded`.
  - `personal` always returns MIME-decoded value.
  - `host` always returns IDN-decoded value.
  - `encode`/`idn` parameters to `writeAddress()` changed.
  - Added `host_idn`, `valid`. Renamed `full_address` to `bare_address`.
- `Horde_Mail_Rfc822_Group`
  - No longer accessible as an array.
  - Removed `groupname_decoded`. `groupname` always returns MIME-decoded.
  - `encode`/`idn` parameters to `writeAddress()` changed. Added `valid`.
- `Horde_Mail_Rfc822_Object`
  - Added `match()`. Passing `true` to `writeAddress()` now enables full
    encoding.

## Upgrading to 1.2

- `Horde_Mail_Rfc822#parseAddressList()` — first argument accepts
  `Horde_Mail_Rfc822_Object` or array.
- `Horde_Mail_Rfc822_Address` — constructor takes optional address string.
- `Horde_Mail_Rfc822_Group` — constructor takes optional groupname and
  addresses.
- New methods: `Horde_Mail_Rfc822#encode()`, `Horde_Mail_Rfc822#trimAddress()`.
- New base class `Horde_Mail_Rfc822_Object` for `Address` and `Group`.

## Upgrading to 1.1

`Horde_Mail_Rfc822::parseAddressList()` now returns `Horde_Mail_Rfc822_Address`
and `Horde_Mail_Rfc822_Group` objects. These are backward-compatible with the
former array representation.

