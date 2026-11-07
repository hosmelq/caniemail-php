<?php

declare(strict_types=1);

use HosmelQ\CanIEmail\Detection\Detector;

it('detects at-rules and media features', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'grouping and keyframes rules' => [
        '<style>@import url("a.css"); @font-face { font-family: X; src: url(x.woff2) } @-webkit-keyframes spin { from { opacity: 0 } 50% { transform: rotate(1turn) } } @supports (display: grid) { @media screen and (-webkit-min-device-pixel-ratio: 2), (any-hover: hover) { .x { display: grid } } }</style>',
        [
            'html-style',
            'css-at-import',
            'css-at-font-face',
            'css-at-keyframes',
            'css-opacity',
            'css-transform',
            'css-at-supports',
            'css-at-media',
            'css-at-media-device-pixel-ratio',
            'css-at-media-hover',
            'css-selector-class',
            'css-display',
            'css-display-grid',
        ],
    ],
    'nested media conditions' => [
        '<style>@media (not ( hover: hover)) and (min-width: 1px) { }</style>',
        ['html-style', 'css-at-media', 'css-at-media-hover'],
    ],
]);

it('detects CSS comments outside strings', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'a comment in a string' => [
        '<style>a { content: "/*" } b { content: \'/*\' }</style>',
        ['html-style', 'css-selector-type'],
    ],
    'a stylesheet comment' => [
        "<style>/* reset */ a { content: '/*' }</style>",
        ['html-style', 'css-comments', 'css-selector-type'],
    ],
    'an inline style comment' => [
        '<p style="/* x */ color: red"></p>',
        ['html-p', 'css-comments'],
    ],
]);

it('detects CSS functions, keywords and units', function (): void {
    expect(
        Detector::detect(
            '<p style="width: calc((100% - 2EM) / 2); color: oklch(70% 0.1 200); font-family: ui-serif; height: fit-content; margin: initial; padding: 1vh 2vw; --brand: red; border-color: var(--brand)"></p>',
        ),
    )->toBe(
        [
            'html-p',
            'css-width',
            'css-unit-calc',
            'css-unit-percent',
            'css-unit-em',
            'css-modern-color',
            'css-sytem-ui',
            'css-height',
            'css-intrinsic-size',
            'css-margin',
            'css-unit-initial',
            'css-padding',
            'css-unit-vh',
            'css-unit-vw',
            'css-variables',
            'css-border',
        ],
    );
});

it('detects CSS nesting', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'a nested at-rule' => [
        '<style>.n { @media (prefers-color-scheme: dark) { display: none } }</style>',
        [
            'html-style',
            'css-selector-class',
            'css-nesting',
            'css-at-media',
            'css-at-media-prefers-color-scheme',
            'css-display',
            'css-display-none',
        ],
    ],
    'a nested pseudo-class' => [
        '<style>.n { &:hover { display: flex } }</style>',
        [
            'html-style',
            'css-selector-class',
            'css-nesting',
            'css-pseudo-class-hover',
            'css-display',
            'css-display-flex',
        ],
    ],
    'a nested rule' => [
        '<style>.n { color: blue; & .m { color: red } }</style>',
        ['html-style', 'css-selector-class', 'css-nesting', 'css-selector-descendant'],
    ],
    'a nesting selector' => [
        '<style>& a { }</style>',
        ['html-style', 'css-nesting', 'css-selector-descendant', 'css-selector-type'],
    ],
    'declarations around a nested rule' => [
        '<style>.n { font-weight: bold; .m { display: flex } font-size: 12px; }</style>',
        [
            'html-style',
            'css-selector-class',
            'css-font-weight',
            'css-nesting',
            'css-display',
            'css-display-flex',
            'css-font-size',
            'css-unit-px',
        ],
    ],
]);

it('detects CSS properties and values', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'an inline flex container' => [
        '<span style="display: inline-flex"></span>',
        ['html-span', 'css-display', 'css-display-flex'],
    ],
    'logical borders' => [
        '<p style="border-inline-color: red; border-block-start-width: 1px; border-inline-start: none"></p>',
        [
            'html-p',
            'css-border-inline-block-individual',
            'css-border-inline-block-longhand',
            'css-unit-px',
        ],
    ],
    'longhands and shorthands' => [
        '<p style="font-size: 12px; font-family: Arial; border-top-left-radius: 2px; border-top-color: red; overflow-wrap: anywhere; background-position-x: 0"></p>',
        [
            'html-p',
            'css-font-size',
            'css-unit-px',
            'css-border-radius',
            'css-border',
            'css-overflow-wrap',
            'css-background-position',
        ],
    ],
    'property values' => [
        '<p style="display: FLEX; flex-direction: column; flex-wrap: wrap; display: grid !important; display: none"></p>',
        [
            'html-p',
            'css-display',
            'css-display-flex',
            'css-flex-direction',
            'css-flex-wrap',
            'css-important',
            'css-display-grid',
            'css-display-none',
        ],
    ],
    'repeated inline styles' => [
        '<p style="display: flex"></p><p style="display: flex"></p>',
        ['html-p', 'css-display', 'css-display-flex'],
    ],
]);

it('detects HTML attributes, comments and doctypes', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'AMP for Email' => [
        '<html amp4email><body></body></html>',
        ['amp', 'html-body'],
    ],
    'AMP for Email with the lightning bolt' => [
        '<html ⚡4email><body></body></html>',
        ['amp', 'html-body'],
    ],
    'attributes without their pair' => [
        '<img sizes="1px"><button commandfor="x"></button>',
        ['html-img', 'html-srcset', 'html-command-attribute', 'html-button-submit'],
    ],
    'default input and button types' => [
        '<input><button></button>',
        ['html-input-text', 'html-button-submit'],
    ],
    'input and button types' => [
        '<input type="CHECKBOX"><button type="reset"></button><input type="email">',
        ['html-input-checkbox', 'html-button-reset'],
    ],
    'local links' => [
        '<a href="#top"></a>',
        ['html-anchor-links'],
    ],
    'mailto links' => [
        '<a href=" MAILTO:a@example.com"></a>',
        ['html-mailto-links'],
    ],
    'named anchors' => [
        '<a name="top"></a>',
        ['html-anchor-links'],
    ],
    'Outlook conditional comments' => [
        '<!--[if mso]><table><![endif]--><p></p>',
        ['html-comments', 'html-p'],
    ],
    'the color-scheme meta tag' => [
        '<meta name="Color-Scheme" content="light dark">',
        ['html-meta-color-scheme'],
    ],
    'the HTML5 doctype' => [
        '<!DOCTYPE html><p></p>',
        ['html-doctype', 'html-p'],
    ],
    'the XHTML doctype' => [
        '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><p></p>',
        ['html-p'],
    ],
]);

it('detects image formats', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'CSS images' => [
        '<p style="background-image: url(a.svg), url(\'b.ico\'), url(data:image/heic;base64,AA)"></p>',
        ['html-p', 'css-background-image', 'image-svg', 'image-ico', 'image-heif', 'image-base64'],
    ],
    'HTML images' => [
        '<img src="photo.JPEG?w=1#x"><img src="data:image/png;base64,AAAA"><picture><source srcset="a.avif 1x, data:image/webp;base64,UklG 2x"></picture><table background="bg.gif"></table><img src="clip.mp4"><img src="https://example.com/image">',
        [
            'html-img',
            'image-jpg',
            'image-png',
            'image-base64',
            'html-picture',
            'html-srcset',
            'image-avif',
            'image-webp',
            'html-table',
            'html-background',
            'image-gif',
            'image-mp4',
        ],
    ],
]);

it('detects selectors', function (string $html, array $slugs): void {
    expect(Detector::detect($html))->toBe($slugs);
})->with([
    'combinators without spaces' => [
        '<style>a>b{color:red}</style>',
        ['html-style', 'css-selector-type', 'css-selector-child'],
    ],
    'descendant classes' => [
        '<style>.x .y {}</style>',
        ['html-style', 'css-selector-class', 'css-selector-descendant'],
    ],
    'every kind of selector' => [
        '<style>.a.b > p + i ~ b, div *, #id [data-x], a:hover::before, li:nth-child(2n), p:after, td:LANG(en) { color: red }</style>',
        [
            'html-style',
            'css-selector-grouping',
            'css-selector-class',
            'css-selector-chaining',
            'css-selector-child',
            'css-selector-type',
            'css-selector-adjacent-sibling',
            'css-selector-general-sibling',
            'css-selector-descendant',
            'css-selector-universal',
            'css-selector-id',
            'css-selector-attribute',
            'css-pseudo-class-hover',
            'css-pseudo-element-before',
            'css-pseudo-class-nth-child',
            'css-pseudo-element-after',
            'css-pseudo-class-lang',
        ],
    ],
    'selectors in pseudo-class arguments' => [
        '<style>a:not(:first-child):has(> img[src]), li:nth-child(2n of .x), li:nth-child(odd), p:lang(en), h1:is(#a, h3) {}</style>',
        [
            'html-style',
            'css-selector-grouping',
            'css-selector-type',
            'css-pseudo-class-not',
            'css-pseudo-class-first-child',
            'css-pseudo-class-has',
            'css-selector-child',
            'css-selector-attribute',
            'css-pseudo-class-nth-child',
            'css-selector-class',
            'css-pseudo-class-lang',
            'css-selector-id',
        ],
    ],
    'two grouped selectors' => [
        '<style>h1, h2 {}</style>',
        ['html-style', 'css-selector-grouping', 'css-selector-type'],
    ],
]);

it('ignores markup and CSS it does not know', function (): void {
    expect(
        Detector::detect(
            '<x-card hx-get="/"><style>@x; :root {} }} ;</style><p style="{{ }}: ;"></p></x-card>',
        ),
    )->toBe(['html-style', 'html-p']);
});
