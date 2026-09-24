<?php

declare(strict_types=1);

namespace App\Config;

use App\Analyzer\Graph\NodeKind;

/**
 * The three questions inspection answers without requiring a graph query.
 */
enum InspectFilter: string
{
    case All = 'all';
    case Calls = 'calls';
    case Depend = 'depend';

    /**
     * Chooses the useful question for the kind of symbol the user named.
     */
    public static function forKind(NodeKind $kind): self
    {
        return match ($kind) {
            NodeKind::Method, NodeKind::Function => self::Calls,
            NodeKind::Klass, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum => self::Depend,
            NodeKind::Constant, NodeKind::EnumCase, NodeKind::Property, NodeKind::Builtin, NodeKind::Unknown => self::All,
        };
    }
}
