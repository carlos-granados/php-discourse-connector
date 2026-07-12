# php-discourse-connector

A bridge that makes the read-only Discourse mirror of the
[PHP internals mailing list](https://discourse.thephp.foundation/c/mailing-lists/php-internals/)
**writable**: when a user posts or replies in the Discourse category, this service sends a properly
formatted email to the mailing list on their behalf.

## How it works

- Each Discourse user gets a **surrogate email address** on a domain we control
  (e.g. `jane.doe+example.com.x7k2@discourse.thephp.foundation` for `jane.doe@example.com`).
- Surrogates are subscribed to the list in ezmlm's **nomail** mode
  (`internals+subscribe-nomail@lists.php.net`): they may post but receive no list mail, adding no
  load to the list server. Subscription confirmations are answered automatically via a catch-all
  IMAP mailbox.
- On `post_created` webhooks (HMAC-verified), the post's raw markdown is converted to a plain-text
  email and sent to the list as `"<Name> via Discourse" <surrogate>`, with correct `In-Reply-To` /
  `References` threading. Bodies carry no added footers — they look like regular list mail.
- The list→Discourse direction remains handled by Discourse's existing mirroring.

## Stack

Symfony 7.4 LTS on PHP 8.5 (FrankenPHP), PostgreSQL 17, Symfony Messenger (Doctrine transport),
Mailpit for local mail. Everything runs in Docker — no host PHP required.

## Development

```sh
docker compose up -d --wait      # start app (:8080), postgres, mailpit (UI at :8025)
docker compose exec app sh      # shell in the app container
```

All tooling runs as composer scripts inside the app container:

```sh
docker compose exec app composer test        # PHPUnit + Behat
docker compose exec app composer stan        # PHPStan (level max)
docker compose exec app composer cs          # coding-standards check (cs:fix to apply)
docker compose exec app composer rector      # rector dry-run (rector:fix to apply)
docker compose exec app composer ci          # all of the above
```

Health check: `curl localhost:8080/healthz`
