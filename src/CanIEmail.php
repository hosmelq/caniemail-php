<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail;

use function Safe\file_get_contents;
use function Safe\json_decode;

use HosmelQ\CanIEmail\Detection\Detector;
use HosmelQ\CanIEmail\Enums\Category;
use HosmelQ\CanIEmail\Enums\ClientFamily;
use HosmelQ\CanIEmail\Enums\ClientId;
use HosmelQ\CanIEmail\Enums\Support;
use HosmelQ\CanIEmail\Exceptions\InvalidData;
use HosmelQ\CanIEmail\Exceptions\UnknownClient;

final class CanIEmail
{
    private static null|self $bundled = null;

    /**
     * @param list<Client> $clients
     * @param array<string, Feature> $features
     * @param array<string, array<string, list<string>>> $notes
     * @param array<string, array<string, Support>> $support
     */
    private function __construct(
        private readonly array $clients,
        private readonly array $features,
        private readonly array $notes,
        private readonly array $support,
    ) {}

    public static function create(): self
    {
        return self::$bundled ??= self::fromJson(
            file_get_contents(__DIR__.'/../data/caniemail.json'),
        );
    }

    public static function fromJson(string $json): self
    {
        $data = self::array(json_decode($json, true));
        $nicenames = self::array($data['nicenames'] ?? null);
        $familyNames = self::strings($nicenames['family'] ?? null);
        $platformNames = self::strings($nicenames['platform'] ?? null);
        $clientIds = [];
        $clients = [];
        $features = [];
        $notes = [];
        $support = [];

        foreach (self::array($data['data'] ?? null) as $item) {
            $item = self::array($item);
            $slug = self::string($item['slug'] ?? null);
            $notesByNumber = self::strings($item['notes_by_num'] ?? []);
            $features[$slug] = new Feature(
                Category::tryFrom(self::string($item['category'] ?? null))
                    ?? throw new InvalidData(),
                $slug,
                mb_trim(self::string($item['title'] ?? null)),
                self::string($item['url'] ?? null),
            );

            foreach (self::array($item['stats'] ?? null) as $family => $platforms) {
                foreach (self::array($platforms) as $platform => $versions) {
                    $clientId = $clientIds[$family][$platform] ??= "{$family}.{$platform}";
                    $versions = self::array($versions);
                    $references = explode(' #', self::string(end($versions)));
                    $support[$slug][$clientId] = Support::tryFrom(array_shift($references))
                        ?? throw new InvalidData();
                    $clientNotes = array_values(
                        array_intersect_key($notesByNumber, array_flip($references)),
                    );

                    if ($clientNotes !== []) {
                        $notes[$slug][$clientId] = $clientNotes;
                    }
                }
            }
        }

        foreach ($familyNames as $family => $familyName) {
            foreach ($platformNames as $platform => $platformName) {
                if (isset($clientIds[$family][$platform])) {
                    $clients[] = new Client(
                        (string) $family,
                        $familyName,
                        (string) $platform,
                        $platformName,
                    );
                }
            }
        }

        return new self($clients, $features, $notes, $support);
    }

    /**
     * @param list<ClientFamily|ClientId|string> $clients
     *
     * @return list<Issue>
     */
    public function check(string $html, array $clients = ['*']): array
    {
        $selected = $this->select($clients);
        $issues = [];

        foreach (array_intersect(Detector::detect($html), array_keys($this->features)) as $slug) {
            foreach ($selected as $client) {
                $issue = $this->issue($this->features[$slug], $client);

                if ($issue instanceof Issue) {
                    $issues[] = $issue;
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<Client>
     */
    public function clients(): array
    {
        return $this->clients;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function array(mixed $value): array
    {
        return is_array($value) ? $value : throw new InvalidData();
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : throw new InvalidData();
    }

    /**
     * @return array<array-key, string>
     */
    private static function strings(mixed $value): array
    {
        $values = self::array($value);
        $strings = array_filter($values, is_string(...));

        return count($strings) === count($values) ? $strings : throw new InvalidData();
    }

    private function issue(Feature $feature, Client $client): null|Issue
    {
        $support = $this->support[$feature->slug][$client->id] ?? Support::Unknown;

        return $support === Support::Full
            ? null
            : new Issue(
                $client,
                $feature,
                $this->notes[$feature->slug][$client->id] ?? [],
                $support,
            );
    }

    /**
     * @param list<ClientFamily|ClientId|string> $patterns
     *
     * @return list<Client>
     */
    private function select(array $patterns): array
    {
        $selected = [];

        foreach ($patterns as $pattern) {
            $pattern = match (true) {
                $pattern instanceof ClientFamily => "{$pattern->value}.*",
                $pattern instanceof ClientId => $pattern->value,
                default => $pattern,
            };
            $matches = array_filter(
                $this->clients,
                static fn (Client $client): bool => fnmatch($pattern, $client->id),
            );

            if ($matches === []) {
                throw UnknownClient::matching($pattern);
            }

            $selected += $matches;
        }

        ksort($selected);

        return array_values($selected);
    }
}
