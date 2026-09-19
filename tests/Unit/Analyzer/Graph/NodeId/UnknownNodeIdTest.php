<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph\NodeId;

use App\Analyzer\Graph\NodeId\UnknownNodeId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UnknownNodeId::class)]
#[Small]
final class UnknownNodeIdTest extends TestCase
{
    #[DataProvider('providerNamesAnUnresolvedSymbolCanCarry')]
    public function testToStringIsWhateverNameTheSymbolWasReferredBy(string $written): void
    {
        self::assertSame($written, (new UnknownNodeId($written))->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerNamesAnUnresolvedSymbolCanCarry(): iterable
    {
        yield 'a namespaced class' => ['App\Domain\Missing'];

        yield 'a member of a class' => ['App\Domain\Invoice::total'];

        yield 'a file standing in for the global scope' => ['/project/src/bootstrap.php'];

        yield 'a global function' => ['array_map'];
    }

    public function testTheNameIsKeptAsItWasGiven(): void
    {
        self::assertSame('App\Domain\Missing', (new UnknownNodeId('App\Domain\Missing'))->name);
    }

    public function testTwoReferencesToTheSameMissingSymbolShareAnIdentifier(): void
    {
        self::assertSame(
            (new UnknownNodeId('App\Domain\Missing'))->toString(),
            (new UnknownNodeId('App\Domain\Missing'))->toString(),
        );
    }
}
