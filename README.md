# Developer Portal PHP Client

[![Branch workflow](https://github.com/keboola/developer-portal-php-client/actions/workflows/branch.yml/badge.svg)](https://github.com/keboola/developer-portal-php-client/actions/workflows/branch.yml)

PHP Client library for [Keboola Developer Portal](https://kebooladeveloperportal.docs.apiary.io/#).

## Rollout waves

Read methods accept an optional rollout `wave` (integer 0-9). With a wave, the Developer Portal
overlays the image-coupled fields (`repository.tag`/`digest`, schemas, sync actions) pinned for
that wave onto the latest definition. Without it (`null`, the default), no `wave` is sent and the
response is the same as before.

```php
$client->adminListApps(null, 2);
$client->adminListAppsPaginated(null, 0, 1000, 2);
$client->adminGetApp('keboola.ex-db-mysql', ['published' => true, 'wave' => 2]);
$client->publicGetAppDetail('keboola.ex-db-mysql', 2);
```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
