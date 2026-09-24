<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Declaration\PhpDoc;

use App\Analyzer\Declaration\PhpDoc\DocOwners;
use App\Analyzer\Declaration\PhpDoc\DocScope;
use App\Analyzer\Graph\Graph;
use App\Analyzer\Graph\Node\PropertyNode;
use App\Analyzer\Graph\NodeId\PropertyNodeId;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesNamespace;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DocOwners::class)]
#[UsesNamespace('App\Analyzer')]
#[Small]
final class DocOwnersTest extends TestCase
{
    public function testOfOnePropertyStatementFindsEveryDeclaredProperty(): void
    {
        $graph = new Graph();
        $first = new PropertyNode(PropertyNodeId::of('Subject', 'first'), true);
        $second = new PropertyNode(PropertyNodeId::of('Subject', 'second'), true);
        $graph->addNodes([$first, $second]);
        $scope = new DocScope(new NameContext(new Collecting()), 'Subject');
        $statement = new Property(0, [new PropertyItem('first'), new PropertyItem('second')]);

        self::assertSame([$first, $second], DocOwners::of($statement, $scope, $graph));
        self::assertNull(DocOwners::of(new Nop(), $scope, $graph));
        self::assertNull(DocOwners::of(new ClassMethod('unusedTraitMethod'), $scope, $graph));
    }
}
