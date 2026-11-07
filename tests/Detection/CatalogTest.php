<?php

declare(strict_types=1);

use HosmelQ\CanIEmail\Detection\Catalog;

it('maps every feature of the bundled data except BIMI and HDR images', function (): void {
    $slugs = iterator_to_array(
        new RecursiveIteratorIterator(
            new RecursiveArrayIterator(new ReflectionClass(Catalog::class)->getConstants()),
        ),
        false,
    );
    $data = json_decode(
        file_get_contents(__DIR__.'/../../data/caniemail.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(array_values(array_unique($slugs)))->toEqualCanonicalizing(
        array_values(array_diff(array_column($data['data'], 'slug'), ['bimi', 'image-hdr'])),
    );
});
