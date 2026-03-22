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
     * Inverts the edge kind to create a reverse relationship.
     *
     * Usage edges (function calls, property access, etc.) invert to UsedBy.
     * Declaration edges invert to DeclaredIn.
     * UsedBy and DeclaredIn edges cannot be inverted and will throw an exception.
     *
     * @return self The inverted edge kind
     *
     * @throws \LogicException If attempting to invert a UsedBy or DeclaredIn edge
     */
    public function invert(): self
    {
        return match ($this) {
            self::FunctionCall,
            self::MethodCall,
            self::StaticCall,
            self::Instantiation,
            self::PropertyAccess,
            self::StaticPropertyAccess,
            self::ConstFetch => self::UsedBy,

            self::DeclarationTraitUse,
            self::DeclarationExtends,
            self::DeclarationImplements,
            self::DeclarationMethod,
            self::DeclarationProperty,
            self::DeclarationConstant,
            self::DeclarationEnumCase,
            self::DeclarationTypeParameter,
            self::DeclarationTypeReturn,
            self::DeclarationTypeProperty => self::DeclaredIn,

            self::Attribute,
            self::Instanceof,
            self::Catch => self::UsedBy,

            self::UsedBy,
            self::DeclaredIn => throw new \LogicException('Cannot reverse a reversed edge'),
        };
    }
}
