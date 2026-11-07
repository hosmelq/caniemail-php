<?php

declare(strict_types=1);

use HosmelQ\CanIEmail\CanIEmail;
use HosmelQ\CanIEmail\Client;
use HosmelQ\CanIEmail\Enums\Category;
use HosmelQ\CanIEmail\Enums\ClientFamily;
use HosmelQ\CanIEmail\Enums\ClientId;
use HosmelQ\CanIEmail\Enums\Support;
use HosmelQ\CanIEmail\Exceptions\InvalidData;
use HosmelQ\CanIEmail\Exceptions\UnknownClient;
use HosmelQ\CanIEmail\Feature;
use HosmelQ\CanIEmail\Issue;
use Safe\Exceptions\JsonException;

beforeEach(function (): void {
    $this->canIEmail = CanIEmail::fromJson(file_get_contents(__DIR__.'/Fixtures/caniemail.json'));
});

it('rejects clients and patterns that match no client', function (
    ClientFamily|ClientId|string $client,
    string $pattern,
): void {
    expect(fn (): array => $this->canIEmail->check('<div></div>', ['gmail.*', $client]))
        ->toThrow(UnknownClient::class, "No email client matches \"{$pattern}\".");
})->with([
    'a client case' => [ClientId::YahooIos, 'yahoo.ios'],
    'a family case' => [ClientFamily::Yahoo, 'yahoo.*'],
    'a pattern' => ['yahoo.*', 'yahoo.*'],
]);

it('rejects data that does not match the caniemail format', function (mixed $data): void {
    CanIEmail::fromJson(json_encode($data, JSON_THROW_ON_ERROR));
})->with([
    'a feature with an unknown category' => [[
        'data' => [[
            'category' => 'video',
            'slug' => 'x',
            'stats' => [],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'a feature without a category' => [[
        'data' => [[
            'slug' => 'x',
            'stats' => [],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'a feature without a slug' => [[
        'data' => [[
            'category' => 'css',
            'stats' => [],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'a feature without a title' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'stats' => [],
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'a feature without a URL' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'stats' => [],
            'title' => 'X',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'a feature without stats' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'an unknown support code' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'stats' => ['gmail' => ['android' => ['1' => 'x']]],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'family names that are not strings' => [[
        'data' => [],
        'nicenames' => ['family' => ['gmail' => 1], 'platform' => []],
    ]],
    'family stats that are not objects' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'stats' => ['gmail' => null],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'missing family names' => [[
        'data' => [],
        'nicenames' => ['platform' => []],
    ]],
    'missing features' => [[
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'missing nicenames' => [['data' => []]],
    'missing platform names' => [[
        'data' => [],
        'nicenames' => ['family' => []],
    ]],
    'notes that are not strings' => [[
        'data' => [[
            'category' => 'css',
            'notes_by_num' => ['1' => 1],
            'slug' => 'x',
            'stats' => [],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'platform names that are not strings' => [[
        'data' => [],
        'nicenames' => ['family' => [], 'platform' => ['android' => 1]],
    ]],
    'platform stats that are not objects' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'stats' => ['gmail' => ['android' => 'y']],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
    'versions that are not strings' => [[
        'data' => [[
            'category' => 'css',
            'slug' => 'x',
            'stats' => ['gmail' => ['android' => ['1' => true]]],
            'title' => 'X',
            'url' => 'u',
        ]],
        'nicenames' => ['family' => [], 'platform' => []],
    ]],
])->throws(InvalidData::class, 'The JSON does not match the caniemail data format.');

it('rejects invalid JSON', function (): void {
    CanIEmail::fromJson('{');
})->throws(JsonException::class);

it('checks an email against the bundled data', function (): void {
    $canIEmail = CanIEmail::create();
    $issues = $canIEmail->check('<style>.card { display: flex; }</style><div class="card"></div>');

    expect(array_map(fn (Client $client): string => $client->id, $canIEmail->clients()))
        ->toContain('apple-mail.ios', 'gmail.desktop-webmail', 'outlook.windows')
        ->and(array_map(fn (Issue $issue): Support => $issue->support, $issues))
        ->not->toBeEmpty()
        ->not->toContain(Support::Full);
});

it('checks every client by default', function (): void {
    $issues = $this->canIEmail->check('<p style="display: flex"></p>');

    expect(array_map(fn (Issue $issue): string => $issue->client->id, $issues))
        ->toBe(['gmail.android', 'gmail.ios', 'outlook.windows']);
});

it('checks the clients given as enum cases', function (array $clients, array $ids): void {
    $issues = $this->canIEmail->check('<p style="display: flex"></p>', $clients);

    expect(array_map(fn (Issue $issue): string => $issue->client->id, $issues))->toBe($ids);
})->with([
    'a client' => [[ClientId::OutlookWindows], ['outlook.windows']],
    'a family' => [[ClientFamily::Gmail], ['gmail.android', 'gmail.ios']],
]);

it('checks the matching clients once, in the order of the data', function (): void {
    $issues = $this->canIEmail->check('<p style="display: flex"></p>', [
        'outlook.*',
        'gmail.i*',
        'outlook.windows',
    ]);

    expect(array_map(fn (Issue $issue): string => $issue->client->id, $issues))
        ->toBe(['gmail.ios', 'outlook.windows']);
});

it('has an enum case named after each bundled client and family', function (): void {
    $cases = [...ClientFamily::cases(), ...ClientId::cases()];
    $clients = CanIEmail::create()->clients();

    expect(array_column(ClientId::cases(), 'value'))
        ->toEqualCanonicalizing(array_map(fn (Client $client): string => $client->id, $clients))
        ->and(array_column(ClientFamily::cases(), 'value'))
        ->toEqualCanonicalizing(array_values(array_unique(array_map(
            fn (Client $client): string => $client->family,
            $clients,
        ))))
        ->and(array_column($cases, 'name'))
        ->toBe(array_map(
            fn (ClientFamily|ClientId $case): string
                => str_replace(['.', '-'], '', ucwords($case->value, '.-')),
            $cases,
        ));
});

it('lists the tested clients in the order of the data', function (): void {
    expect($this->canIEmail->clients())->toEqual([
        new Client('gmail', 'Gmail', 'android', 'Android'),
        new Client('gmail', 'Gmail', 'desktop-webmail', 'Desktop Webmail'),
        new Client('gmail', 'Gmail', 'ios', 'iOS'),
        new Client('outlook', 'Outlook', 'windows', 'Windows'),
    ]);
});

it('reads the last version caniemail lists', function (): void {
    expect($this->canIEmail->check('<p style="display: flex"></p>', ['gmail.android']))->toEqual([
        new Issue(
            new Client('gmail', 'Gmail', 'android', 'Android'),
            new Feature(
                Category::Css,
                'css-display-flex',
                'display:flex',
                'https://www.caniemail.com/features/css-display-flex/',
            ),
            ['Not supported.'],
            Support::None,
        ),
    ]);
});

it('reports clients without data as unknown', function (): void {
    $issues = $this->canIEmail->check('<p style="display: flex"></p>', ['gmail.ios']);

    expect($issues[0]->support)->toBe(Support::Unknown)->and($issues[0]->notes)->toBe([]);
});

it('reports issues in the order the features appear', function (): void {
    $issues = $this->canIEmail->check(
        '<p style="color: rgb(0 0 0)"></p><p style="display: flex"></p>',
        ['gmail.android'],
    );

    expect(array_map(fn (Issue $issue): string => $issue->feature->slug, $issues))
        ->toBe(['css-rgb', 'css-display-flex']);
});

it('reuses the bundled data', function (): void {
    expect(CanIEmail::create())->toBe(CanIEmail::create());
});

it('skips features that are fully supported or missing from the data', function (): void {
    expect($this->canIEmail->check('<div><p></p></div>'))->toBe([]);
});

it('skips notes that the data does not have', function (): void {
    $issues = $this->canIEmail->check('<p style="display: flex"></p>', ['outlook.windows']);

    expect($issues[0]->notes)->toBe(['Partial support.']);
});

it('trims feature titles', function (): void {
    $issues = $this->canIEmail->check('<p style="color: rgb(0 0 0)"></p>', ['gmail.android']);

    expect($issues[0]->feature->title)->toBe('rgb()');
});
