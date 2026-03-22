<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Enumeration of different types of nodes in the dependency graph.
 *
 * Each case represents a specific type of PHP code element that can be
 * represented as a node in the dependency graph.
 */
enum NodeKind: string
{
    /** Represents a PHP class */
    case Klass = 'class';

    /** Represents a class constant */
    case Constant = 'constant';

    /** Represents an enum case */
    case EnumCase = 'enum_case';

    /** Represents a PHP enum */
    case Enum = 'enum';

    /** Represents a global function */
    case Function = 'function';

    /** Represents a PHP interface */
    case Interface = 'interface';

    /** Represents a class method */
    case Method = 'method';

    /** Represents a class property */
    case Property = 'property';

    /** Represents a PHP trait */
    case Trait = 'trait';

    /** Represents a builtin PHP type (e.g., string, int, array) */
    case Builtin = 'builtin';

    /** Represents an unknown or unresolved type */
    case Unknown = 'unknown';
}
