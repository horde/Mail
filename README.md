# Horde Mail

Email address parsing (RFC 5322), mail transport, and mbox parsing (RFC 4155).

Part of the [Horde](https://www.horde.org/) framework.

## Installation

```bash
composer require horde/mail
```

## Upgrading

See [doc/UPGRADING.md](doc/UPGRADING.md) for API changes between releases and
a full migration guide from the legacy `lib/` classes to the modern `src/` API.

## Collaborator libraries

| Package | Role |
|---|---|
| [horde/stream](https://github.com/horde/Stream) | Typed stream wrappers (`StreamInterface`, `Temp`) used by transports and mbox parser |
| [horde/smtp](https://github.com/horde/Smtp) | SMTP/LMTP protocol client used by `SmtpTransport` and `LmtpTransport` (optional) |
| [horde/eventdispatcher](https://github.com/horde/EventDispatcher) | PSR-14 event dispatcher for parser observability |
| [horde/idna](https://github.com/horde/Idna) | Internationalized domain name encoding |
| [horde/mime](https://github.com/horde/Mime) | MIME header encoding/decoding (legacy `lib/` dependency) |
| [horde/socket_client](https://github.com/horde/Socket_Client) | TCP socket abstraction used by `horde/smtp` (optional) |

## Relevant RFCs

- [RFC 5322](https://datatracker.ietf.org/doc/html/rfc5322) — Internet Message Format (address syntax)
- [RFC 6532](https://datatracker.ietf.org/doc/html/rfc6532) — Internationalized Email Headers (EAI)
- [RFC 2047](https://datatracker.ietf.org/doc/html/rfc2047) — MIME Message Header Extensions (encoded words)
- [RFC 4155](https://datatracker.ietf.org/doc/html/rfc4155) — The application/mbox Media Type
- [RFC 5321](https://datatracker.ietf.org/doc/html/rfc5321) — Simple Mail Transfer Protocol

## License

BSD-2-Clause. See [LICENSE](LICENSE).
