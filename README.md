# Developer Portal PHP Client

[![Branch workflow](https://github.com/keboola/developer-portal-php-client/actions/workflows/branch.yml/badge.svg)](https://github.com/keboola/developer-portal-php-client/actions/workflows/branch.yml)

PHP Client library for [Keboola Developer Portal](https://kebooladeveloperportal.docs.apiary.io/#).

## Rollout waves

The admin read methods accept an optional rollout `wave` (integer 0-9). With a wave, the Developer
Portal serves the version pinned for that wave: its tag, digest, schemas and sync actions on top of
the latest definition. Without it (`null`, the default), no `wave` is sent and the response is the
same as before. Public endpoints don't support waves.

```php
$client->adminListApps(null, 2);
$client->adminListAppsPaginated(null, 0, 1000, 2);
$client->adminGetApp('keboola.ex-db-mysql', ['published' => true, 'wave' => 2]);
```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
