<?php

declare(strict_types=1);

namespace Spec\Context;

use RuntimeException;
use SimpleXMLElement;

/**
 * The grammar ISO/IEC 39075 specifies, read from ISO's own artifact.
 *
 * The artifact's header says it is published so that implementers can generate parsers
 * for GQL from it, which makes it the one place a claim about GQL's syntax can be
 * settled without a reader having to take an implementer's word for anything.
 *
 * Three questions are asked of it here, and they are the three an implementation is
 * tempted to answer from memory: which words the standard reserves, which words it
 * writes at all, and which of those it applies arguments to. Each costs nothing to get
 * wrong until a query that means one thing in GQL means another here.
 *
 * @see spec/iso/artifacts.txt Where ISO publishes the artifact, and its sum
 */
final class IsoGrammar
{
    /**
     * Returns every word the standard reserves, in the order the artifact writes them.
     *
     * The standard reserves in two productions and means both: `<reserved word>`, and
     * the `<pre-reserved word>`s it holds back for later editions. An implementation
     * that honours only the first accepts names that a later edition will take away.
     *
     * @return list<string> The reserved words
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function reservedWords(): array
    {
        return [...self::production('reserved word'), ...self::production('pre-reserved word')];
    }

    /**
     * Returns every word the grammar writes anywhere, reserved or not.
     *
     * A word an implementation spells that is not in here is a word of the
     * implementation's own. It may be a useful one — `CONTAINS` is useful — but a
     * reader who knows GQL does not know it, which is the whole cost the command
     * exists to avoid paying.
     *
     * @return list<string> The words, in no particular order
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function keywords(): array
    {
        static $keywords = null;
        if ($keywords !== null) {
            return $keywords;
        }

        $found = [];
        foreach (self::grammar()->xpath('//kw') ?? [] as $keyword) {
            $found[trim((string) $keyword)] = true;
        }
        $keywords = array_keys($found);

        return $keywords;
    }

    /**
     * Returns every name the grammar writes a left parenthesis after.
     *
     * A word being somewhere in the grammar does not make it a function: `LABELS`
     * appears, in the syntax for declaring a graph type, and `labels(x)` is still not
     * something GQL says. So what is collected here is narrower — the words the
     * grammar actually applies arguments to, wherever it does so, including through a
     * production like `<general set function type>` that stands for a list of them.
     *
     * @return list<string> The names, in no particular order
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function calledNames(): array
    {
        static $names = null;
        if ($names !== null) {
            return $names;
        }

        $found = [];
        foreach (self::grammar()->xpath('//rhs') ?? [] as $sequence) {
            foreach (self::callsIn($sequence) as $name) {
                $found[$name] = true;
            }
        }
        $names = array_keys($found);

        return $names;
    }

    /**
     * Returns the names a left parenthesis follows anywhere inside one sequence.
     *
     * @param SimpleXMLElement $sequence The sequence
     *
     * @return list<string> The names
     */
    private static function callsIn(SimpleXMLElement $sequence): array
    {
        $found = [];
        $previous = null;
        foreach ($sequence->children() as $child) {
            if ($child->getName() === 'BNF' && (string) $child['name'] === 'left paren' && $previous !== null) {
                $found = [...$found, ...self::opensWith($previous, [])];
            }
            if (in_array($child->getName(), ['alt', 'group', 'opt', 'rep'], true)) {
                $found = [...$found, ...self::callsIn($child)];
            }
            $previous = $child;
        }

        return $found;
    }

    /**
     * Returns every keyword one element of the grammar can begin with.
     *
     * @param SimpleXMLElement $element The element
     * @param list<string>     $seen    The productions already being followed, so a cycle ends
     *
     * @return list<string> The keywords
     */
    private static function opensWith(SimpleXMLElement $element, array $seen): array
    {
        if ($element->getName() === 'kw') {
            return [trim((string) $element)];
        }
        if ($element->getName() === 'BNF') {
            return self::opensProduction((string) $element['name'], $seen);
        }
        if (!in_array($element->getName(), ['alt', 'group', 'opt', 'rep'], true)) {
            return [];
        }

        $found = [];
        foreach ($element->children() as $child) {
            $found = [...$found, ...self::opensWith($child, $seen)];
        }

        return $found;
    }

    /**
     * Returns every keyword one named production can begin with.
     *
     * @param string       $name The production's name
     * @param list<string> $seen The productions already being followed
     *
     * @return list<string> The keywords
     */
    private static function opensProduction(string $name, array $seen): array
    {
        if (in_array($name, $seen, true)) {
            return [];
        }
        $defined = self::grammar()->xpath(sprintf('//BNFdef[@name=%s]/rhs', self::quoted($name)));
        if ($defined === null || $defined === []) {
            return [];
        }

        $found = [];
        foreach ($defined[0]->children() as $child) {
            $found = [...$found, ...self::opensWith($child, [...$seen, $name])];
            if ($child->getName() !== 'alt') {
                break;
            }
        }

        return $found;
    }

    /**
     * Reports whether the grammar defines a production.
     *
     * @param string $name The production's name, as the artifact spells it
     *
     * @return bool True when it does
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    public static function defines(string $name): bool
    {
        $found = self::grammar()->xpath(sprintf('//BNFdef[@name=%s]', self::quoted($name)));

        return $found !== null && $found !== [];
    }

    /**
     * Returns the keywords one production of the grammar is written from.
     *
     * @param string $name The production's name, as the artifact spells it
     *
     * @return list<string> The keywords, in the order the artifact writes them
     *
     * @throws RuntimeException If the artifact cannot be read, or defines no such production
     */
    public static function production(string $name): array
    {
        $found = self::grammar()->xpath(sprintf('//BNFdef[@name=%s]', self::quoted($name)));
        if ($found === null || $found === []) {
            throw new RuntimeException(sprintf('ISO/IEC 39075 defines no production called <%s>.', $name));
        }

        $keywords = [];
        foreach ($found[0]->xpath('.//kw') ?? [] as $keyword) {
            $keywords[] = trim((string) $keyword);
        }

        return $keywords;
    }

    /**
     * Returns the artifact, read once.
     *
     * @return SimpleXMLElement The grammar
     *
     * @throws RuntimeException If the artifact cannot be read
     */
    private static function grammar(): SimpleXMLElement
    {
        static $grammar = null;
        if ($grammar instanceof SimpleXMLElement) {
            return $grammar;
        }

        $read = @simplexml_load_file(IsoArtifacts::path('gql.bnf.xml'));
        if (!$read instanceof SimpleXMLElement) {
            throw new RuntimeException(sprintf('Cannot read the ISO grammar artifact at %s', IsoArtifacts::path('gql.bnf.xml')));
        }
        $grammar = $read;

        return $grammar;
    }

    /**
     * Returns a value XPath will read as the string it is.
     *
     * @param string $value The value
     *
     * @return string The value, quoted
     */
    private static function quoted(string $value): string
    {
        return "'".$value."'";
    }
}
