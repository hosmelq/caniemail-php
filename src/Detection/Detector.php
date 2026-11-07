<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail\Detection;

use function Safe\preg_match_all;
use function Safe\preg_replace;

use Dom\Comment;
use Dom\DocumentType;
use Dom\Element;
use Dom\HTMLDocument;
use Wikimedia\CSS\Objects\AtRule;
use Wikimedia\CSS\Objects\ComponentValue;
use Wikimedia\CSS\Objects\ComponentValueList;
use Wikimedia\CSS\Objects\CSSFunction;
use Wikimedia\CSS\Objects\DeclarationList;
use Wikimedia\CSS\Objects\QualifiedRule;
use Wikimedia\CSS\Objects\RuleList;
use Wikimedia\CSS\Objects\SimpleBlock;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Parser\Parser;

final class Detector
{
    private const array COMBINATORS = [
        '+' => Catalog::ADJACENT_SIBLING_COMBINATOR,
        '>' => Catalog::CHILD_COMBINATOR,
        '~' => Catalog::GENERAL_SIBLING_COMBINATOR,
    ];

    private const array DEFAULT_TYPES = [
        'button' => 'submit',
        'input' => 'text',
    ];

    private const array GROUPING_RULES = [
        'container',
        'document',
        'layer',
        'media',
        'scope',
        'starting-style',
        'supports',
    ];

    private const array SELECTOR_ARGUMENTS = [
        'has',
        'is',
        'not',
        'where',
    ];

    private const array SELECTOR_OF_ARGUMENTS = [
        'nth-child',
        'nth-last-child',
    ];

    /**
     * @var array<string, true>
     */
    private array $slugs = [];

    /**
     * @var array<string, true>
     */
    private array $styles = [];

    /**
     * @return list<string>
     */
    public static function detect(string $html): array
    {
        return new self()->document(
            HTMLDocument::createFromString($html, LIBXML_HTML_NOIMPLIED | LIBXML_NOERROR),
        );
    }

    private function add(string ...$slugs): void
    {
        foreach ($slugs as $slug) {
            $this->slugs[$slug] = true;
        }
    }

    private function atRule(AtRule $rule, bool $nested): void
    {
        $name = preg_replace('/^-[a-z]+-/', '', mb_strtolower($rule->getName()));
        $block = $rule->getBlock();

        $this->add(...Catalog::AT_RULES[$name] ?? []);

        if ($name === 'media') {
            $this->mediaFeatures($rule->getPrelude());
        }

        if (! $block instanceof SimpleBlock) {
            return;
        }

        if ($nested) {
            $this->block($block->getValue());
        } elseif (in_array($name, self::GROUPING_RULES, true)) {
            $this->rules($this->parse($block->getValue())->parseRuleList(), selectors: true);
        } elseif ($name === 'keyframes') {
            $this->rules($this->parse($block->getValue())->parseRuleList(), selectors: false);
        }
    }

    private function block(ComponentValueList $values): void
    {
        $declarations = [];
        $part = [];

        foreach ($values->toComponentValueArray() as $value) {
            $part[] = $value;

            if (
                $value instanceof SimpleBlock
                && $value->getStartTokenType() === Token::T_LEFT_BRACE
            ) {
                $this->declarations(
                    $this->parse(new ComponentValueList($declarations))->parseDeclarationList(),
                );
                $this->nested(new ComponentValueList($part));

                $declarations = [];
                $part = [];
            } elseif ($this->tokenIs($value, Token::T_SEMICOLON)) {
                array_push($declarations, ...$part);

                $part = [];
            }
        }

        $this->declarations(
            $this->parse(new ComponentValueList([...$declarations, ...$part]))
                ->parseDeclarationList(),
        );
    }

    private function children(Element|HTMLDocument $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof Comment) {
                $this->add(Catalog::HTML_COMMENTS);
            } elseif ($child instanceof Element) {
                $this->element($child);
                $this->children($child);
            }
        }
    }

    private function combinator(ComponentValue $value): null|string
    {
        return array_find(
            self::COMBINATORS,
            fn (string $slug, string $character): bool => $this->isDelimiter($value, $character),
        );
    }

    private function comments(string $css): void
    {
        $code = preg_replace('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/s', '', $css);

        if (str_contains($code, '/*')) {
            $this->add(Catalog::CSS_COMMENTS);
        }
    }

    private function declarations(DeclarationList $declarations): void
    {
        foreach ($declarations->slice(0) as $declaration) {
            $property = mb_strtolower($declaration->getName());
            $value = $declaration->getValue();

            $this->add(...$this->property($property));

            if ($declaration->getImportant()) {
                $this->add(Catalog::IMPORTANT);
            }

            $this->add(
                ...Catalog::VALUES[$property][mb_strtolower(mb_trim((string) $value))] ?? [],
            );
            $this->values($value);
        }
    }

    /**
     * @return list<string>
     */
    private function document(HTMLDocument $document): array
    {
        $doctype = $document->doctype;

        if (
            $doctype instanceof DocumentType
            && $doctype->name === 'html'
            && $doctype->publicId === ''
            && $doctype->systemId === ''
        ) {
            $this->add(Catalog::DOCTYPE);
        }

        $this->children($document);

        return array_keys($this->slugs);
    }

    private function element(Element $element): void
    {
        $name = $element->localName;
        $type = mb_strtolower($element->getAttribute('type') ?? self::DEFAULT_TYPES[$name] ?? '');
        $style = $element->getAttribute('style');

        $this->add(...Catalog::ELEMENTS[$name] ?? []);

        foreach ($element->attributes as $attribute) {
            $this->add(...Catalog::ATTRIBUTES[$attribute->localName] ?? []);
        }

        $this->add(...Catalog::TYPES[$name][$type] ?? []);

        if ($name === 'a') {
            $this->link($element);
        } elseif (
            $name === 'html'
            && ($element->hasAttribute('⚡4email') || $element->hasAttribute('amp4email'))
        ) {
            $this->add(Catalog::AMP);
        } elseif (
            $name === 'meta'
            && mb_strtolower($element->getAttribute('name') ?? '') === 'color-scheme'
        ) {
            $this->add(Catalog::COLOR_SCHEME_META);
        } elseif ($name === 'style') {
            $this->stylesheet($element->textContent ?? '');
        }

        $this->images($element);

        if ($style !== null) {
            $this->style($style);
        }
    }

    private function image(string $url): void
    {
        $url = mb_strtolower(mb_trim($url));

        if (str_starts_with($url, 'data:')) {
            [$header] = explode(',', mb_substr($url, 5), 2);

            $parameters = explode(';', $header);

            $this->add(...Catalog::IMAGE_TYPES[$parameters[0]] ?? []);

            if (in_array('base64', $parameters, true)) {
                $this->add(Catalog::BASE64_IMAGE);
            }

            return;
        }

        $path = preg_replace('/[?#].*/s', '', $url);

        $this->add(...Catalog::IMAGE_EXTENSIONS[pathinfo($path, PATHINFO_EXTENSION)] ?? []);
    }

    private function images(Element $element): void
    {
        $background = $element->getAttribute('background');
        $source = $element->localName === 'img' ? $element->getAttribute('src') : null;
        $srcset = $element->getAttribute('srcset') ?? '';

        preg_match_all(
            '/[\s,]*([^\s,](?:\S*[^\s,])?)(?:,+|(?:\s+[^\s,]+)*\s*(?:,|$))/',
            $srcset,
            $matches,
        );

        foreach ([$source, ...$matches[1], $background] as $url) {
            if ($url !== null) {
                $this->image($url);
            }
        }
    }

    private function isDelimiter(null|ComponentValue $value, string $character): bool
    {
        return $this->tokenIs($value, Token::T_DELIM) && $value->value() === $character;
    }

    private function link(Element $link): void
    {
        $href = mb_strtolower(mb_trim($link->getAttribute('href') ?? ''));

        if (str_starts_with($href, '#') || $link->hasAttribute('name')) {
            $this->add(Catalog::ANCHOR_LINKS);
        }

        if (str_starts_with($href, 'mailto:')) {
            $this->add(Catalog::MAILTO_LINKS);
        }
    }

    private function mediaFeatures(ComponentValueList $values): void
    {
        foreach ($values->toComponentValueArray() as $value) {
            if (! $value instanceof SimpleBlock) {
                continue;
            }

            $feature = array_find(
                $value->getValue()->toComponentValueArray(),
                fn (ComponentValue $component): bool
                    => ! $this->tokenIs($component, Token::T_WHITESPACE),
            );

            if ($this->tokenIs($feature, Token::T_IDENT)) {
                $name = preg_replace(
                    '/^(-[a-z]+-)?(?:max|min)-/',
                    '$1',
                    mb_strtolower((string) $feature->value()),
                );

                $this->add(...Catalog::MEDIA_FEATURES[$name] ?? []);
            }

            $this->mediaFeatures($value->getValue());
        }
    }

    private function nested(ComponentValueList $values): void
    {
        $rule = $this->parse($values)->parseRule();

        $this->add(Catalog::CSS_NESTING);

        if ($rule instanceof AtRule) {
            $this->atRule($rule, nested: true);
        } elseif ($rule instanceof QualifiedRule) {
            $this->selectors($rule->getPrelude());
            $this->block($rule->getBlock()->getValue());
        }
    }

    private function parse(ComponentValueList $values): Parser
    {
        return Parser::newFromTokens($values->toTokenArray());
    }

    /**
     * @return list<string>
     */
    private function property(string $name): array
    {
        if (str_starts_with($name, '--')) {
            return [Catalog::CSS_VARIABLES];
        }

        if (isset(Catalog::PROPERTIES[$name])) {
            return Catalog::PROPERTIES[$name];
        }

        $shorthand = '';

        foreach (array_keys(Catalog::PROPERTY_PREFIXES) as $prefix) {
            if (str_starts_with($name, $prefix.'-') && mb_strlen($prefix) > mb_strlen($shorthand)) {
                $shorthand = $prefix;
            }
        }

        return Catalog::PROPERTY_PREFIXES[$shorthand] ?? [];
    }

    private function pseudoFunction(CSSFunction $pseudo): void
    {
        $name = mb_strtolower($pseudo->getName());
        $arguments = array_values($pseudo->getValue()->toComponentValueArray());

        $this->add(...Catalog::PSEUDOS[$name] ?? []);

        if (in_array($name, self::SELECTOR_OF_ARGUMENTS, true)) {
            $of = array_find_key(
                $arguments,
                fn (ComponentValue $value): bool => $this->tokenIs($value, Token::T_IDENT)
                    && mb_strtolower((string) $value->value()) === 'of',
            );
            $arguments = $of === null ? [] : array_slice($arguments, $of + 1);
        } elseif (! in_array($name, self::SELECTOR_ARGUMENTS, true)) {
            return;
        }

        foreach ($this->split($arguments) as $selector) {
            $this->selector($selector);
        }
    }

    private function rules(RuleList $rules, bool $selectors): void
    {
        foreach ($rules->slice(0) as $rule) {
            if ($rule instanceof AtRule) {
                $this->atRule($rule, nested: false);
            } elseif ($rule instanceof QualifiedRule) {
                if ($selectors) {
                    $this->selectors($rule->getPrelude());
                }

                $this->block($rule->getBlock()->getValue());
            }
        }
    }

    /**
     * @param list<ComponentValue> $values
     */
    private function selector(array $values): void
    {
        $classes = 0;
        $compound = false;
        $descendant = false;

        foreach ($values as $index => $value) {
            if ($this->tokenIs($value, Token::T_WHITESPACE)) {
                $descendant = $compound;

                continue;
            }

            $combinator = $this->combinator($value);

            if ($combinator !== null) {
                $this->add($combinator);

                $classes = 0;
                $compound = false;
                $descendant = false;

                continue;
            }

            if ($descendant) {
                $this->add(Catalog::DESCENDANT_COMBINATOR);

                $classes = 0;
                $descendant = false;
            }

            $compound = true;
            $previous = $values[$index - 1] ?? null;

            if ($this->isDelimiter($value, '.')) {
                $this->add(Catalog::CLASS_SELECTOR);

                if (++$classes === 2) {
                    $this->add(Catalog::CHAINING_SELECTORS);
                }
            } elseif ($this->isDelimiter($value, '*')) {
                $this->add(Catalog::UNIVERSAL_SELECTOR);
            } elseif ($this->isDelimiter($value, '&')) {
                $this->add(Catalog::CSS_NESTING);
            } elseif ($this->tokenIs($value, Token::T_HASH)) {
                $this->add(Catalog::ID_SELECTOR);
            } elseif (
                $value instanceof SimpleBlock
                && $value->getStartTokenType() === Token::T_LEFT_BRACKET
            ) {
                $this->add(Catalog::ATTRIBUTE_SELECTOR);
            } elseif ($value instanceof CSSFunction && $this->tokenIs($previous, Token::T_COLON)) {
                $this->pseudoFunction($value);
            } elseif ($this->tokenIs($value, Token::T_IDENT)) {
                if ($this->tokenIs($previous, Token::T_COLON)) {
                    $this->add(...Catalog::PSEUDOS[mb_strtolower((string) $value->value())] ?? []);
                } elseif (! $this->isDelimiter($previous, '.')) {
                    $this->add(Catalog::TYPE_SELECTOR);
                }
            }
        }
    }

    private function selectors(ComponentValueList $prelude): void
    {
        $selectors = $this->split($prelude->toComponentValueArray());

        if (count($selectors) > 1) {
            $this->add(Catalog::GROUPING_SELECTORS);
        }

        foreach ($selectors as $selector) {
            $this->selector($selector);
        }
    }

    /**
     * @param array<ComponentValue> $values
     *
     * @return non-empty-list<list<ComponentValue>>
     */
    private function split(array $values): array
    {
        $selectors = [[]];

        foreach ($values as $value) {
            if ($this->tokenIs($value, Token::T_COMMA)) {
                $selectors[] = [];
            } else {
                $selectors[array_key_last($selectors)][] = $value;
            }
        }

        return $selectors;
    }

    private function style(string $declarations): void
    {
        if (isset($this->styles[$declarations])) {
            return;
        }

        $this->styles[$declarations] = true;

        $this->comments($declarations);
        $this->declarations(Parser::newFromString($declarations)->parseDeclarationList());
    }

    private function stylesheet(string $css): void
    {
        $this->comments($css);
        $this->rules(
            Parser::newFromString($css)->parseStylesheet()->getRuleList(),
            selectors: true,
        );
    }

    /**
     * @phpstan-assert-if-true =Token $value
     */
    private function tokenIs(null|ComponentValue $value, string $type): bool
    {
        return $value instanceof Token && $value->type() === $type;
    }

    private function values(ComponentValueList $values): void
    {
        foreach ($values->toComponentValueArray() as $value) {
            if ($value instanceof CSSFunction) {
                $name = mb_strtolower($value->getName());

                $this->add(...Catalog::FUNCTIONS[$name] ?? []);

                if ($name === 'url') {
                    foreach ($value->getValue()->toComponentValueArray() as $argument) {
                        if ($this->tokenIs($argument, Token::T_STRING)) {
                            $this->image((string) $argument->value());
                        }
                    }
                }

                $this->values($value->getValue());
            } elseif ($value instanceof SimpleBlock) {
                $this->values($value->getValue());
            } elseif ($this->tokenIs($value, Token::T_DIMENSION)) {
                $this->add(...Catalog::UNITS[mb_strtolower($value->unit())] ?? []);
            } elseif ($this->tokenIs($value, Token::T_IDENT)) {
                $this->add(...Catalog::KEYWORDS[mb_strtolower((string) $value->value())] ?? []);
            } elseif ($this->tokenIs($value, Token::T_PERCENTAGE)) {
                $this->add(...Catalog::UNITS['%']);
            } elseif ($this->tokenIs($value, Token::T_URL)) {
                $this->image((string) $value->value());
            }
        }
    }
}
