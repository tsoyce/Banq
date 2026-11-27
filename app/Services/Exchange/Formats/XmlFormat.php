<?php

namespace App\Services\Exchange\Formats;

use App\Services\Exchange\EntityRegistry;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use XMLWriter;

/**
 * A39 · XML: тот же пакет, что JSON, только в тегах.
 *
 *   <banq version="1">
 *     <banks>
 *       <row><country>KZ</country><code>BCC</code><aliases><item>ЦентрКредит</item></aliases>
 *         <deposit_products><row><name>Срочный</name><type>term</type>
 *           <rates><row><term_months>12</term_months><rate>14.5</rate></row></rates></row></deposit_products></row>
 *     </banks>
 *   </banq>
 *
 * Чтение прощает чужие выгрузки: корень любой (или сам — сущность: <banks><bank>…</bank></banks>),
 * строка — любой тег, поля — дочерние теги или атрибуты (<row code="BCC" name="…"/>).
 * Список — повторяющиеся теги, <item>/<row>, или множественное → единственное (<rates><rate/></rates>).
 * Ключи, которые не могут быть именем тега, пишутся как <entry key="…">.
 * Внешние сущности (XXE) не загружаются.
 */
class XmlFormat implements Format
{
    private const LIST_TAGS = ['row', 'item', 'value', 'li'];

    public function key(): string
    {
        return 'xml';
    }

    public function extension(): string
    {
        return 'xml';
    }

    public function mime(): string
    {
        return 'application/xml';
    }

    public function read(string $content, ?string $entity = null): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim($content));
        if (preg_match('/<!DOCTYPE/i', $content)) {
            throw new InvalidArgumentException('XML с DOCTYPE не принимаем (защита от внешних сущностей)');
        }
        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($content, LIBXML_NONET | LIBXML_COMPACT);
        $error = libxml_get_last_error();
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok || ! $doc->documentElement) {
            throw new InvalidArgumentException('Это не XML: '.trim($error?->message ?? 'пустой документ')." (строка {$error?->line})");
        }

        $root = $doc->documentElement;
        $registry = app(EntityRegistry::class);
        // корень — сама сущность (<banks>…</banks>) или явно указанная entity при «плоском» корне
        if ($registry->find($root->localName) || ($entity && ! collect(self::children($root))->contains(fn ($c) => $registry->find($c->localName)))) {
            return [($registry->find($root->localName)?->key() ?? $entity) => self::rows($root)];
        }

        $out = [];
        foreach (self::children($root) as $el) {
            $out[$el->localName] = [...($out[$el->localName] ?? []), ...self::rows($el)];
        }

        return $out;
    }

    /** Строки сущности: каждый дочерний тег — строка. */
    private static function rows(DOMElement $el): array
    {
        return array_map(fn (DOMElement $row) => (array) self::value($row, true), self::children($el));
    }

    /** @return list<DOMElement> */
    private static function children(DOMElement $el): array
    {
        $out = [];
        foreach ($el->childNodes as $c) {
            if ($c instanceof DOMElement) {
                $out[] = $c;
            }
        }

        return $out;
    }

    private static function value(DOMElement $el, bool $isRow = false): mixed
    {
        $children = self::children($el);
        $attrs = [];
        foreach ($el->attributes as $a) {
            if ($a->localName !== 'key' && $a->localName !== 'nil') {
                $attrs[$a->localName] = $a->value;
            }
        }
        if ($el->getAttribute('nil') === 'true') {
            return null;
        }
        if (! $children) {
            if ($isRow || ($attrs && trim($el->textContent) === '')) {
                return $attrs;
            }

            return $el->textContent;
        }

        $names = array_map(fn (DOMElement $c) => $c->hasAttribute('key') ? $c->getAttribute('key') : $c->localName, $children);
        $first = $children[0]->localName;
        $sameTag = collect($children)->every(fn ($c) => $c->localName === $first && ! $c->hasAttribute('key'));
        $plural = in_array($el->localName, [$first.'s', $first.'es', preg_replace('/y$/', 'ies', $first)], true);
        if (! $isRow && $sameTag && (in_array($first, self::LIST_TAGS, true) || count($children) > 1 || $plural)) {
            return array_map(fn ($c) => self::value($c), $children);
        }

        $out = $attrs;
        foreach ($children as $i => $c) {
            $name = $names[$i];
            $v = self::value($c);
            if (array_key_exists($name, $out)) {
                $out[$name] = is_array($out[$name]) && array_is_list($out[$name]) ? [...$out[$name], $v] : [$out[$name], $v];
            } else {
                $out[$name] = $v;
            }
        }

        return $out;
    }

    public function write(array $bundle): string
    {
        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('banq');
        $w->writeAttribute('version', '1');
        $w->writeAttribute('exported_at', now()->toIso8601String());
        foreach ($bundle as $entity => $rows) {
            $w->startElement($entity);
            foreach ($rows as $row) {
                $w->startElement('row');
                self::writeMap($w, (array) $row);
                $w->endElement();
            }
            $w->endElement();
        }
        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    private static function writeMap(XMLWriter $w, array $map): void
    {
        foreach ($map as $k => $v) {
            if ($v === null || $v === []) {
                continue;
            }
            $k = (string) $k;
            if (preg_match('/^[\p{L}_][\p{L}\p{N}_.-]*$/u', $k) && ! str_starts_with(strtolower($k), 'xml')) {
                $w->startElement($k);
            } else {
                $w->startElement('entry');
                $w->writeAttribute('key', $k);
            }
            self::writeValue($w, $v);
            $w->endElement();
        }
    }

    private static function writeValue(XMLWriter $w, mixed $v): void
    {
        if (is_array($v) && array_is_list($v)) {
            foreach ($v as $item) {
                $w->startElement('item');
                self::writeValue($w, $item);
                $w->endElement();
            }
        } elseif (is_array($v)) {
            self::writeMap($w, $v);
        } elseif (is_bool($v)) {
            $w->text($v ? 'true' : 'false');
        } elseif ($v === null) {
            $w->writeAttribute('nil', 'true');
        } else {
            $w->text(is_float($v) ? rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.') : (string) $v);
        }
    }
}
