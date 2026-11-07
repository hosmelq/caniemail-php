# Can I email PHP

Check which email clients support the HTML and CSS in an email, using the support data from
[caniemail.com](https://www.caniemail.com).

## Requirements

- PHP 8.4+

## Installation

Install Can I email PHP with Composer:

```bash
composer require hosmelq/caniemail
```

## Check an email

```php
use HosmelQ\CanIEmail\CanIEmail;

$issues = CanIEmail::create()->check($html, ['gmail.*', 'outlook.windows']);

foreach ($issues as $issue) {
    echo "{$issue->feature->title}: {$issue->support->name} in {$issue->client->familyName} {$issue->client->platformName}\n";
}
```

`check()` returns an `Issue` for each feature the email uses and each client that does not fully
support it. Issues follow the order of the elements where each feature first appears. Each issue
has the `client`, the `feature` with its caniemail.com `url`, the `support` and caniemail's
`notes` for that client.

`support` is a `HosmelQ\CanIEmail\Enums\Support` case: `None`, `Partial` or `Unknown`. Like
caniemail.com, it comes from the last version caniemail lists for the client. Clients caniemail
has not tested for a feature report `Support::Unknown`.

`create()` loads the bundled data on its first call and returns that same instance after that.
`fromJson()` builds a new instance on every call.

## Choose clients

Pass client IDs or patterns. An ID joins a family and a platform, such as `apple-mail.ios`, and
patterns use `*`:

```php
$issues = CanIEmail::create()->check($html, ['gmail.*', '*.ios', 'outlook.windows']);
```

`HosmelQ\CanIEmail\Enums\ClientFamily` has a case for each family in the bundled data and
`HosmelQ\CanIEmail\Enums\ClientId` one for each client, named after their IDs. A family case
such as `ClientFamily::Gmail` checks every Gmail platform, like `gmail.*`, and a client case such
as `ClientId::AppleMailIos` checks `apple-mail.ios`. Cases and patterns can share a list:

```php
use HosmelQ\CanIEmail\Enums\ClientFamily;
use HosmelQ\CanIEmail\Enums\ClientId;

$issues = CanIEmail::create()->check($html, [
    ClientFamily::Gmail,
    ClientId::OutlookWindows,
    '*.ios',
]);
```

If you leave out the clients, `check()` checks all of them, and an empty list checks none. List
the clients with `clients()`:

```php
foreach (CanIEmail::create()->clients() as $client) {
    echo "{$client->id}: {$client->familyName} {$client->platformName}\n";
}
```

A case or pattern that matches no client throws `HosmelQ\CanIEmail\Exceptions\UnknownClient`.

## Use newer data

Each release bundles the caniemail.com data of its date. To check against newer data, pass
caniemail's JSON to `fromJson()`:

```php
$canIEmail = CanIEmail::fromJson(file_get_contents('https://www.caniemail.com/api/data.json'));
```

Features that caniemail adds after a release are detected once a release maps them. Clients it
adds work as IDs and patterns right away, and get enum cases in a later release. Data that
does not match caniemail's format throws `HosmelQ\CanIEmail\Exceptions\InvalidData`, and invalid
JSON throws `Safe\Exceptions\JsonException`.

## What it detects

The checks read the HTML and the CSS in `<style>` elements and `style` attributes:

- HTML elements, attributes, input and button types, local and `mailto:` links, the
  `color-scheme` meta tag, the HTML5 doctype, comments and AMP for Email.
- CSS properties, values, units, functions, keywords, selectors, comments, nesting, `@media`
  features and other at-rules.
- The image formats of `<img src>`, `srcset` and `background` attributes and CSS `url()` values.

BIMI and HDR images can't be detected from HTML, so they are never reported.

## Development

Run the test suite with:

```bash
composer test
```

Download the latest caniemail.com data with:

```bash
composer update-data
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Contributing

Pull requests are welcome. Please run the test suite before submitting changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. The
caniemail.com data is copyright Rémi Parmentier and published under the MIT license in
[data/LICENSE.md](data/LICENSE.md).
