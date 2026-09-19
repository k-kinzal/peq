<?php

declare(strict_types=1);

namespace App\Gql\Element;

use App\Analyzer\Graph\Declaration\SymbolDeclaration;
use App\Analyzer\Graph\Node;
use App\Analyzer\Graph\QualifiedName;
use App\Gql\Datum\BooleanDatum;
use App\Gql\Datum\Datum;
use App\Gql\Datum\IntegerDatum;
use App\Gql\Datum\ListDatum;
use App\Gql\Datum\StringDatum;

/**
 * What a query can ask a symbol about itself.
 *
 * Everything the analysis knows is offered as a property, because a property is the
 * only thing a GQL expression can read. What is not offered cannot be filtered on,
 * sorted by or returned — so the list here is, in practice, the list of questions
 * peq can answer about a single symbol.
 *
 * A property the source says nothing about is left out rather than given a stand-in
 * value. That is what makes `p.visibility IS NULL` mean "this kind of symbol has no
 * visibility" rather than "it is package-private", and it is why a pattern can match
 * classes and methods together and ask each of them what it knows.
 *
 * @visibility App\Gql
 */
final class NodeProperties
{
    /**
     * Returns everything a query can ask a symbol.
     *
     * @param Node $node The symbol
     *
     * @example A symbol always knows what it is and what it is called
     *     $named = \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Invoice', 'total');
     *     \App\Gql\Element\NodeProperties::of(new \App\Analyzer\Graph\Node\MethodNode($named, true))['name']->toText() // => 'total'
     * @example A member knows which class-like declares it
     *     $named = \App\Analyzer\Graph\NodeId\MethodNodeId::of('App\\Domain\\Invoice', 'total');
     *     \App\Gql\Element\NodeProperties::of(new \App\Analyzer\Graph\Node\MethodNode($named, true))['owner']->toText() // => 'App\\Domain\\Invoice'
     *
     * @return array<string, Datum> The properties, by name
     */
    public static function of(Node $node): array
    {
        $id = $node->id()->toString();
        $properties = [
            'id' => new StringDatum($id),
            'kind' => new StringDatum($node->kind()->value),
            'resolved' => new BooleanDatum($node->resolved()),
            ...self::naming($id),
            ...self::location($node),
        ];

        $declared = $node->declaration();

        return $declared === null ? $properties : [...$properties, ...self::declared($declared)];
    }

    /**
     * Returns every property a symbol can carry and what kind of value it is.
     *
     * Not every symbol carries every one of these. A class has no visibility, a
     * constant has no signature, and a symbol analysis only ever saw referred to
     * carries nothing but its name — which is why a query asks `IS NULL` rather than
     * assuming. What the list promises is only that nothing else is offered.
     *
     * @example What a declaration says is offered beside what its name says
     *     \App\Gql\Element\NodeProperties::all()['visibility'] // => 'STRING'
     * @example So is the shape a callable's callers were written against
     *     \App\Gql\Element\NodeProperties::all()['parameters'] // => 'LIST<STRING>'
     *
     * @return array<string, string> The property names, and the GQL type of each
     */
    public static function all(): array
    {
        return [
            'id' => 'STRING',
            'kind' => 'STRING',
            'name' => 'STRING',
            'namespace' => 'STRING',
            'owner' => 'STRING',
            'resolved' => 'BOOL',
            'file' => 'STRING',
            'fileName' => 'STRING',
            'line' => 'INT64',
            'column' => 'INT64',
            'visibility' => 'STRING',
            'static' => 'BOOL',
            'abstract' => 'BOOL',
            'final' => 'BOOL',
            'readonly' => 'BOOL',
            'deprecated' => 'BOOL',
            'attributes' => 'LIST<STRING>',
            'type' => 'STRING',
            'value' => 'STRING',
            'signature' => 'STRING',
            'returnType' => 'STRING',
            'parameters' => 'LIST<STRING>',
            'parameterTypes' => 'LIST<STRING>',
            'parameterCount' => 'INT64',
        ];
    }

    /**
     * Returns what a symbol's own name says about it.
     *
     * A member is written as its owner and its own name joined by two colons, which
     * is the form a reader writes and the form a target is written in on the command
     * line. Splitting it here rather than asking each kind of identifier for its
     * parts keeps one rule for eleven kinds of symbol.
     *
     * @param string $id The symbol's fully qualified name
     *
     * @example A member is split into what declares it and what it is called
     *     \App\Gql\Element\NodeProperties::naming('App\\Domain\\Invoice::total')['owner']->toText() // => 'App\\Domain\\Invoice'
     * @example A class-like is split into its namespace and its short name
     *     \App\Gql\Element\NodeProperties::naming('App\\Domain\\Invoice')['namespace']->toText() // => 'App\\Domain'
     *
     * @return array<string, Datum> The naming properties, by name
     */
    public static function naming(string $id): array
    {
        $separator = strpos($id, '::');
        if ($separator === false) {
            $qualified = new QualifiedName($id);

            return ['name' => new StringDatum($qualified->shortName), 'namespace' => new StringDatum($qualified->namespace)];
        }

        $owner = substr($id, 0, $separator);

        return [
            'name' => new StringDatum(substr($id, $separator + 2)),
            'namespace' => new StringDatum((new QualifiedName($owner))->namespace),
            'owner' => new StringDatum($owner),
        ];
    }

    /**
     * Returns where a symbol is written, when analysis knows.
     *
     * @param Node $node The symbol
     *
     * @example A symbol analysis only referred to is written nowhere it knows of
     *     $named = \App\Analyzer\Graph\NodeId\ClassNodeId::of('App\\Missing');
     *     \App\Gql\Element\NodeProperties::location(new \App\Analyzer\Graph\Node\ClassNode($named)) // => []
     *
     * @return array<string, Datum> The location properties, by name
     */
    public static function location(Node $node): array
    {
        $meta = $node->meta();
        if ($meta === null) {
            return [];
        }

        return [
            'file' => new StringDatum($meta->path),
            'fileName' => new StringDatum($meta->name),
            'line' => new IntegerDatum($meta->line),
            'column' => new IntegerDatum($meta->column),
        ];
    }

    /**
     * Returns what a symbol's declaration says about it.
     *
     * @param SymbolDeclaration $declared What the source declares
     *
     * @example A declaration offers what a query can select a symbol by
     *     $says = new \App\Analyzer\Graph\Declaration\SymbolDeclaration(
     *         visibility: \App\Analyzer\Graph\Declaration\Visibility::Public,
     *     );
     *     \App\Gql\Element\NodeProperties::declared($says)['visibility']->toText() // => 'public'
     * @example Keywords are offered whether or not they were written
     *     $says = new \App\Analyzer\Graph\Declaration\SymbolDeclaration();
     *     \App\Gql\Element\NodeProperties::declared($says)['static']->toText() // => 'FALSE'
     *
     * @return array<string, Datum> The declared properties, by name
     */
    public static function declared(SymbolDeclaration $declared): array
    {
        $properties = [
            'static' => new BooleanDatum($declared->modifiers->static),
            'abstract' => new BooleanDatum($declared->modifiers->abstract),
            'final' => new BooleanDatum($declared->modifiers->final),
            'readonly' => new BooleanDatum($declared->modifiers->readonly),
            'deprecated' => new BooleanDatum($declared->deprecated),
            'attributes' => new ListDatum(array_map(
                static fn (string $name): Datum => new StringDatum($name),
                $declared->attributeNames(),
            )),
        ];
        if ($declared->visibility !== null) {
            $properties['visibility'] = new StringDatum($declared->visibility->value);
        }
        if ($declared->type !== null) {
            $properties['type'] = new StringDatum($declared->type);
        }
        if ($declared->value !== null) {
            $properties['value'] = new StringDatum($declared->value);
        }

        return [...$properties, ...self::signature($declared)];
    }

    /**
     * Returns what a callable's signature says about it.
     *
     * @param SymbolDeclaration $declared What the source declares
     *
     * @example A callable offers the shape its callers were written against
     *     $signature = new \App\Analyzer\Graph\Declaration\Signature(
     *         [new \App\Analyzer\Graph\Declaration\Parameter('amount', 'int')],
     *         'void',
     *     );
     *     $says = new \App\Analyzer\Graph\Declaration\SymbolDeclaration(signature: $signature);
     *     \App\Gql\Element\NodeProperties::signature($says)['signature']->toText() // => '(int $amount): void'
     * @example A symbol that is not callable offers none of it
     *     \App\Gql\Element\NodeProperties::signature(new \App\Analyzer\Graph\Declaration\SymbolDeclaration()) // => []
     *
     * @return array<string, Datum> The signature properties, by name
     */
    public static function signature(SymbolDeclaration $declared): array
    {
        $signature = $declared->signature;
        if ($signature === null) {
            return [];
        }

        $text = static fn (string $written): Datum => new StringDatum($written);
        $properties = [
            'signature' => new StringDatum($signature->toString()),
            'parameters' => new ListDatum(array_map($text, $signature->parameterNames())),
            'parameterTypes' => new ListDatum(array_map($text, $signature->parameterTypes())),
            'parameterCount' => new IntegerDatum(count($signature->parameters)),
        ];
        if ($signature->returnType !== null) {
            $properties['returnType'] = new StringDatum($signature->returnType);
        }

        return $properties;
    }
}
