<?php

declare(strict_types=1);

namespace App\Analyzer\Graph\Call;

use App\Analyzer\Graph\FileMeta;
use App\Analyzer\Graph\Node;

/**
 * A written invocation, independent of how many targets analysis resolves for it.
 */
final readonly class CallSite
{
    /**
     * @param list<CallArgument> $arguments Arguments in written order, including names and unpacking
     */
    public function __construct(
        public Node $caller,
        public Node $owner,
        public FileMeta $meta,
        public int $endOffset,
        public string $expression,
        public array $arguments,
        public bool $callableReference = false,
    ) {}

    /**
     * Identifies the written site, independently of argument contents and resolved targets.
     */
    public function id(): string
    {
        return $this->caller->id()->toString().'@'.$this->meta->path.':'.$this->meta->offset.':'.$this->endOffset;
    }

    /**
     * Source facts included in engine equivalence checks and occurrence identities.
     *
     * @return array<string, bool|int|list<null|string>|string>
     */
    public function facts(): array
    {
        return [
            'callSite' => $this->id(),
            'enclosingSymbol' => $this->owner->id()->toString(),
            'expression' => $this->expression,
            'endOffset' => $this->endOffset,
            'callableReference' => $this->callableReference,
            'arguments' => array_map(static fn (CallArgument $argument): string => $argument->text, $this->arguments),
            'argumentNames' => array_map(static fn (CallArgument $argument): ?string => $argument->name, $this->arguments),
            'argumentTypes' => array_map(static fn (CallArgument $argument): ?string => $argument->type, $this->arguments),
        ];
    }
}
