<?php

declare(strict_types=1);

namespace App\Analyzer\Graph;

/**
 * Enumeration of different types of relationships (edges) in the dependency graph.
 *
 * Each case represents a specific type of relationship between PHP code elements.
 * Edge kinds are categorized into usage relationships (method calls, property access, etc.)
 * and declaration relationships (class structure, type declarations, etc.).
 */
enum EdgeKind: string
{
    /** Represents a function call relationship */
    case FunctionCall = 'function-call';

    /** Represents an instance method call relationship */
    case MethodCall = 'method-call';

    /** A method body that may execute at a recorded instance call site */
    case PossibleCall = 'possible-call';

    /** Represents a static method call relationship */
    case StaticCall = 'static-call';

    /** Represents a class instantiation (new) relationship */
    case Instantiation = 'instantiation';

    /** Represents an instance property access relationship */
    case PropertyAccess = 'property-access';

    /** Represents a static property access relationship */
    case StaticPropertyAccess = 'static-property-access';

    /** Represents a constant fetch relationship */
    case ConstFetch = 'const-fetch';

    /** Represents a trait use declaration relationship */
    case DeclarationTraitUse = 'declaration-trait-use';

    /** Represents a class extension (extends) declaration relationship */
    case DeclarationExtends = 'declaration-extends';

    /** Represents an interface implementation (implements) declaration relationship */
    case DeclarationImplements = 'declaration-implements';

    /** Represents a method declaration relationship within a class */
    case DeclarationMethod = 'declaration-method';

    /** Represents a property declaration relationship within a class */
    case DeclarationProperty = 'declaration-property';

    /** Represents a constant declaration relationship within a class */
    case DeclarationConstant = 'declaration-constant';

    /** Represents an enum case declaration relationship within an enum */
    case DeclarationEnumCase = 'declaration-enum-case';

    /** Represents a parameter type declaration relationship */
    case DeclarationTypeParameter = 'declaration-type-parameter';

    /** Represents a return type declaration relationship */
    case DeclarationTypeReturn = 'declaration-type-return';

    /** Represents a property type declaration relationship */
    case DeclarationTypeProperty = 'declaration-type-property';

    /** Represents an attribute usage relationship */
    case Attribute = 'attribute';

    /** Represents an instanceof check relationship */
    case Instanceof = 'instanceof';

    /** Represents a caught exception relationship */
    case Catch = 'catch';

    /** Represents a reverse usage relationship (inverse of usage edges) */
    case UsedBy = 'used-by';

    /** Represents a reverse declaration relationship (inverse of declaration edges) */
    case DeclaredIn = 'declared-in';

    /**
     * Returns the direction in which this kind of relation is read.
     *
     * Every kind written in source code — a call, an access, a declaration —
     * reads away from its subject and therefore belongs to Direction::Uses.
     * The two kinds generated for the opposite direction, UsedBy and DeclaredIn,
     * read towards their subject and belong to Direction::UsedBy.
     *
     * This is a total function: adding a case to this enum without classifying
     * it here is a compile-time-visible omission rather than a silent default.
     *
     * @example A relation written in source code reads away from its subject
     *     \App\Analyzer\Graph\EdgeKind::MethodCall->direction() // => \App\Analyzer\Graph\Direction::Uses
     * @example A derived relation reads towards it
     *     \App\Analyzer\Graph\EdgeKind::UsedBy->direction() // => \App\Analyzer\Graph\Direction::UsedBy
     *
     * @return Direction The direction this kind belongs to
     */
    public function direction(): Direction
    {
        return match ($this) {
            self::FunctionCall,
            self::MethodCall,
            self::PossibleCall,
            self::StaticCall,
            self::Instantiation,
            self::PropertyAccess,
            self::StaticPropertyAccess,
            self::ConstFetch,
            self::DeclarationTraitUse,
            self::DeclarationExtends,
            self::DeclarationImplements,
            self::DeclarationMethod,
            self::DeclarationProperty,
            self::DeclarationConstant,
            self::DeclarationEnumCase,
            self::DeclarationTypeParameter,
            self::DeclarationTypeReturn,
            self::DeclarationTypeProperty,
            self::Attribute,
            self::Instanceof,
            self::Catch => Direction::Uses,

            self::UsedBy,
            self::DeclaredIn => Direction::UsedBy,
        };
    }
}
