<?php

declare(strict_types=1);

namespace Tests\Fixture\Analyzer;

/**
 * The sources two analyzers are checked against each other on.
 *
 * An engine written to behave like another one is only as trustworthy as the
 * question it was asked, and "do these two agree on this codebase" is a weak
 * question if the codebase happens to be written in the half of PHP both of them
 * find easy. This corpus is therefore built the other way round: from the list of
 * things the graph model can say — every kind of symbol, every kind of relation —
 * and from the places where the reference engine does something a reimplementation
 * would not guess, such as attributing a trait's members to each using class, or
 * naming an anonymous class after where it stands relative to the working directory.
 *
 * The scenarios are held as source text rather than as files in the repository so
 * that nothing formats them: several of them mean what they mean because of which
 * line something is written on, and a formatter would quietly take that away. One
 * of them is a file PHP itself refuses, which as a file of its own would fail every
 * tool that reads the repository.
 */
final class EquivalenceCorpus
{
    /**
     * Every scenario, by name: the files it is made of, by file name.
     */
    public const SCENARIOS = [
        'typed receivers and possible implementations' => ['Calls.php' => <<<'PHP'
            <?php
            namespace Corpus\Dispatch;
            interface Port { public function execute(): void; }
            interface Narrow extends Port {}
            class Base { public function execute(): void {} }
            final class Service extends Base implements Narrow {}
            final class Other implements Port { public function execute(): void {} }
            final class Controller {
                public function __construct(private Narrow $port) {}
                public function action(): void { $this->port->execute(); $this->port->execute(); }
            }
            function invoke(Port $port): void { $port->execute(); }
            PHP],
        'nullable compound receivers and repeated attributes' => ['Receivers.php' => <<<'PHP'
            <?php
            namespace Corpus\Receivers;
            #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
            class Trace { public function __construct(public string $name) {} }
            interface Port { public function run(): void; }
            interface Tagged {}
            class Both implements Port, Tagged { #[Trace('same'), Trace('same')] public function run(): void {} }
            class Other implements Port { public function run(): void {} }
            class Factory { public function make(): Port { return new Both(); } }
            function compound((Port&Tagged)|null $port): void { $port?->run(); }
            function chained(Factory $factory): void { $factory->make()->run(); }
            function assigned(): void { $port = new Both(); $port->run(); }
            function dynamic($unknown, string $method): void { $unknown->run(); $unknown->$method(); }
            PHP],
        'class-like declarations and what they are built from' => ['Declarations.php' => <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Corpus\Declared;

            use Countable;
            use RuntimeException as Failure;

            #[\Attribute]
            final class Marker { public function __construct(public string $note = '') {} }

            interface Readable { public const FORMAT = 'text'; public function read(): string; }
            interface Writable extends Readable, Countable {}

            trait Timestamps { public ?string $at = null; public function touch(): void {} }

            abstract class Record implements Writable {
                use Timestamps;
                public const KIND = 'record';
                protected int $revision = 0;
                public static string $registry = '';
                public function read(): string { return ''; }
                public function count(): int { return 0; }
            }

            #[Marker('entity')]
            final class Invoice extends Record {
                #[Marker('total')]
                public ?Marker $marker = null;
                public function __construct(
                    #[Marker('promoted')] private readonly Marker|Failure $origin = new Marker(),
                    private (Marker&Countable)|null $refined = null,
                    private int $plain = 0,
                ) {}
                #[Marker('read')]
                public function read(#[Marker('arg')] Marker $marker): Marker|Failure|null { return null; }
            }

            enum Status: string implements Readable {
                #[Marker('open')]
                case Open = 'open';
                case Closed = 'closed';
                public const DEFAULT = self::Open;
                public function read(): string { return $this->value; }
            }

            function record(Marker $marker): Readable|null { return null; }
            PHP],

        'declarations spread over several namespaces of one file' => ['Namespaces.php' => <<<'PHP'
            <?php
            namespace First {
                class Shared { public function make(): Other { return new Other(); } }
                class Other {}
                function helper(): void { helper(); \strlen('a'); }
            }
            namespace Second {
                class Shared { public function make(): \First\Other { return new \First\Other(); } }
                function helper(): void { helper(); }
            }
            namespace {
                class Shared { public function make(): void { strlen('x'); } }
                function helper(): void {}
            }
            PHP],

        'declarations that name the same symbol from two files' => [
            'DuplicateOne.php' => "<?php\nnamespace Corpus\\Dup;\nclass Same { public function one(): void { \$x = new \\Exception(); } }\n",
            'DuplicateTwo.php' => "<?php\nnamespace Corpus\\Dup;\nclass Other { public function two(): Same { return new Same(); } }\n",
        ],

        'every kind of relation a method body can write' => ['Usage.php' => <<<'PHP'
            <?php
            declare(strict_types=1);
            namespace Corpus\Usage;

            class Dependency {
                public const LIMIT = 10;
                public static int $counter = 0;
                public static function make(): self { return new self(); }
            }

            function bare(): void {}

            final class Subject {
                public int $seen = 0;
                public function helper(): int { return 1; }
                public function work(): void {
                    $made = new Dependency();
                    Dependency::make();
                    $limit = Dependency::LIMIT;
                    $name = Dependency::class;
                    $counter = Dependency::$counter;
                    $is = $made instanceof Dependency;
                    bare();
                    \strlen('x');
                    $this->helper();
                    $this?->helper();
                    $seen = $this->seen;
                    $maybe = $this?->seen;
                    try { throw new \RuntimeException(); } catch (Dependency|\LogicException $caught) { $caught->getMessage(); }
                }
            }
            PHP],

        'relations written in functions, closures and arrow functions' => ['Callables.php' => <<<'PHP'
            <?php
            namespace Corpus\Callables;

            class Thing { public static function make(): self { return new self(); } }

            function outer(): void {
                $closure = function () { $made = new Thing(); Thing::make(); };
                $arrow = fn () => new Thing();
                $nested = function () { $deeper = function () { $made = new Thing(); }; };
                inner();
            }

            function inner(): void { \strlen('a'); }

            class Holder {
                public function hold(): void {
                    $closure = function () { $this->hold(); $made = new Thing(); };
                    $arrow = fn () => $this->hold();
                }
            }
            PHP],

        'names that mean different things where they are written' => ['Keywords.php' => <<<'PHP'
            <?php
            namespace Corpus\Keywords;

            class Ancestor_ { public function inherited(): void {} public const FLAG = 1; }

            class Descendant extends Ancestor_ {
                public function act(): void {
                    $self = new self();
                    $static = new static();
                    parent::inherited();
                    static::act();
                    self::act();
                    $flag = parent::FLAG;
                    $name = static::class;
                    $own = self::class;
                }
            }

            class Orphan {
                public function act(): void { $name = parent::class; $x = new self(); }
            }
            PHP],

        'an unqualified call that could mean two functions' => [
            'Fallback.php' => <<<'PHP'
                <?php
                namespace Corpus\Fallback;
                function shared(): void {}
                class Caller {
                    public function call(): void { shared(); strlen('a'); \Corpus\Fallback\shared(); }
                }
                PHP,
            'Global.php' => "<?php\nfunction shared(): void {}\nclass GlobalCaller { public function call(): void { shared(); } }\n",
        ],

        'a trait read once for each class that uses it' => ['Traits.php' => <<<'PHP'
            <?php
            namespace Corpus\Traits;

            class Dependency {}

            trait Shared {
                public const SHARED = 1;
                public ?Dependency $held = null;
                public function shared(): Dependency { $this->held; $this->shared(); return new Dependency(); }
            }

            class FirstUser { use Shared; }
            class SecondUser { use Shared; public function own(): void { $this->shared(); } }
            enum Kind: string { use Shared; case One = 'one'; }
            PHP],

        'a trait used from another file than it is written in' => [
            'TraitFile.php' => "<?php\nnamespace Corpus\\Across;\ntrait Carried {\n    public int \$held = 0;\n\n    public function carry(): void { \$this->held; }\n}\n",
            'UserFile.php' => "<?php\nnamespace Corpus\\Across;\nclass Carrier { use Carried; public function own(): void { \$this->carry(); } }\n",
        ],

        'a class that writes a method its trait also offers' => ['Override.php' => <<<'PHP'
            <?php
            namespace Corpus\Override;

            trait Offered {
                abstract public function required(): void;
                public function both(): void { $x = new \LogicException(); }
                public function only(): void {}
            }

            class Taker {
                use Offered;
                public function required(): void {}
                public function both(): void { $x = new \RuntimeException(); }
            }
            PHP],

        'traits settled by insteadof and renamed by as' => ['Adaptations.php' => <<<'PHP'
            <?php
            namespace Corpus\Adaptations;

            trait Left { public function shared(): void {} public function onlyLeft(): void {} }
            trait Right { public function shared(): void {} public function onlyRight(): void {} }

            class Settled {
                use Left, Right {
                    Left::shared insteadof Right;
                    Right::shared as rightShared;
                    onlyLeft as protected renamedLeft;
                }
            }
            PHP],

        'a trait that uses a trait' => ['Nested.php' => <<<'PHP'
            <?php
            namespace Corpus\Nested;

            trait Innermost { public function innermost(): void {} public int $deep = 0; const INNER = 1; }
            trait Middle { use Innermost; public function middle(): void {} }
            trait Shadowing { use Innermost; public function innermost(): void {} }

            class Outermost { use Middle; }
            class Shadowed { use Shadowing; }
            class Both { use Middle, Shadowing { Middle::innermost insteadof Shadowing; } }
            PHP],

        'a trait that demands a method rather than writing one' => ['Demanded.php' => <<<'PHP'
            <?php
            namespace Corpus\Demanded;

            trait Demanding {
                abstract public function named(): string;
                abstract public function unanswered(): string;
                public function report(): string { return $this->named(); }
            }

            abstract class Ancestor_ { public function named(): string { return 'ancestor'; } }

            class AnsweredByAncestor extends Ancestor_ { use Demanding; }
            class AnsweredByItself { use Demanding; public function named(): string { return 'own'; } public function unanswered(): string { return ''; } }
            abstract class LeftUnanswered { use Demanding; }

            trait Writing { public function named(): string { return 'trait'; } }
            class AnsweredByAnotherTrait { use Demanding, Writing; }
            PHP],

        'a trait nothing uses' => ['Unused.php' => "<?php\nnamespace Corpus\\Unused;\n#[\\Attribute]\nclass Mark {}\n#[Mark]\ntrait Lonely { public int \$held = 0; public function lonely(): void { \$x = new \\Exception(); } }\n"],

        'anonymous classes, including two written on one line' => ['Anonymous.php' => <<<'PHP'
            <?php
            namespace Corpus\Anonymous;

            trait Lent { public function lent(): void {} public const LENT = 1; }

            class Host {
                public function one(): void { $made = new class { public function inner(): void { $x = new \Exception(); } }; }
                public function two(): void { $first = new class { public function a(): void {} }; $second = new class { public function b(): void {} }; }
                public function three(): object { return new class extends \Exception { use Lent; public int $held = 0; }; }
            }

            $top = new class { public function outer(): void { $x = new \RuntimeException(); } };
            PHP],

        'declarations written inside other statements' => ['Nestedness.php' => <<<'PHP'
            <?php
            namespace Corpus\Nestedness;

            function maker(): void {
                class InsideFunction { public function act(): void { $x = new \Exception(); } }
                function insideFunction(): void { $y = new \LogicException(); }
                $anonymous = new class { public function act(): void { $z = new \RuntimeException(); } };
            }

            if (!class_exists('Corpus\Nestedness\Conditional')) {
                class Conditional { public function act(): void { $x = new \DomainException(); } }
            }

            class WithInnerDeclarations {
                public function act(): void {
                    function declaredInMethod(): void { $y = new \LogicException(); }
                    $anonymous = new class { public function act(): void { $z = new \RangeException(); } };
                }
            }
            PHP],

        'code written outside any declaration' => ['TopLevel.php' => <<<'PHP'
            <?php
            namespace Corpus\TopLevel;

            class Reachable { public const HERE = 1; public static function make(): self { return new self(); } }

            $made = new Reachable();
            Reachable::make();
            $constant = Reachable::HERE;
            $closure = function () { return new Reachable(); };
            try { $x = 1; } catch (\Throwable $caught) { $caught->getMessage(); }
            PHP],

        'a file PHP itself would refuse, next to one it would not' => [
            'Broken.php' => "<?php\nnamespace Bracketed { class InBrackets {} }\nclass Unbracketed {}\n",
            'Intact.php' => "<?php\nnamespace Corpus\\Intact;\nclass Survivor { public function act(): void { \$x = new \\Exception(); } }\n",
        ],

        'a parent class the project cannot reach' => ['Unreachable.php' => <<<'PHP'
            <?php
            namespace Corpus\Unreachable;

            class FromNowhere extends \Vendor\NotInstalled\Missing {
                public function act(): void { parent::act(); $x = new parent(); }
            }

            class FromSomewhere extends \RuntimeException {
                public function act(): void { parent::getMessage(); }
            }
            PHP],

        'control flow deep enough to hide a relation in' => ['DeepFlow.php' => <<<'PHP'
            <?php
            namespace Corpus\DeepFlow;

            class Gate { public const OPEN = true; public static int $depth = 0; public static function check(): bool { return true; } }

            final class Walker {
                public int $state = 0;
                public function helper(): bool { return false; }
                public function walk(): void {
                    if (Gate::OPEN) {
                        foreach ([] as $item) {
                            try {
                                while (Gate::check()) {
                                    for ($i = 0; $i < Gate::$depth; $i++) {
                                        $result = match (true) {
                                            $item instanceof Gate => $this->helper(),
                                            default => $this->state,
                                        };
                                    }
                                }
                            } catch (Gate $caught) {
                                $caught->getMessage();
                            } catch (\Throwable $other) {
                                throw new \RuntimeException('', 0, $other);
                            }
                        }
                    }
                }
            }
            PHP],
    ];
}
