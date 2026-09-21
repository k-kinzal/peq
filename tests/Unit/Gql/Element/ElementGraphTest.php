<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Element;

use App\Gql\Datum\EdgeDatum;
use App\Gql\Datum\NodeDatum;
use App\Gql\Element\ElementGraph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ElementGraph::class)]
#[UsesClass(EdgeDatum::class)]
#[UsesClass(NodeDatum::class)]
#[Small]
final class ElementGraphTest extends TestCase
{
    public function testNodesOffersEverySymbolAPatternCanStartFrom(): void
    {
        $controller = new NodeDatum('App\Http\Controller', ['Class']);
        $show = new NodeDatum('App\Http\Controller::show', ['Method']);

        self::assertSame(
            [$controller, $show],
            (new ElementGraph(['App\Http\Controller' => $controller, 'App\Http\Controller::show' => $show], [], []))->nodes(),
        );
    }

    public function testNodesOfAnEmptyGraphOffersNothingToStartFrom(): void
    {
        self::assertSame([], (new ElementGraph([], [], []))->nodes());
    }

    public function testNodeFindsASymbolByWhatIdentifiesIt(): void
    {
        $total = new NodeDatum('App\Domain\Invoice::total', ['Method']);

        self::assertSame($total, (new ElementGraph(['App\Domain\Invoice::total' => $total], [], []))->node('App\Domain\Invoice::total'));
    }

    public function testNodeFindsNothingForASymbolTheGraphDoesNotHold(): void
    {
        $total = new NodeDatum('App\Domain\Invoice::total', ['Method']);

        self::assertNull((new ElementGraph(['App\Domain\Invoice::total' => $total], [], []))->node('App\Nothing'));
    }

    public function testLeavingReadsTheRelationsWrittenFromASymbol(): void
    {
        $extends = new EdgeDatum('controller>kernel', ['extends'], [], 'App\Http\Controller', 'App\Http\Kernel');
        $declares = new EdgeDatum('controller>show', ['declaresMethod'], [], 'App\Http\Controller', 'App\Http\Controller::show');
        $graph = new ElementGraph(
            [],
            ['App\Http\Controller' => [$extends, $declares]],
            ['App\Http\Kernel' => [$extends], 'App\Http\Controller::show' => [$declares]],
        );

        self::assertSame([$extends, $declares], $graph->leaving('App\Http\Controller'));
    }

    public function testLeavingReadsNothingFromASymbolThatWritesNoRelation(): void
    {
        $extends = new EdgeDatum('controller>kernel', ['extends'], [], 'App\Http\Controller', 'App\Http\Kernel');
        $graph = new ElementGraph([], ['App\Http\Controller' => [$extends]], ['App\Http\Kernel' => [$extends]]);

        self::assertSame([], $graph->leaving('App\Http\Kernel'));
    }

    public function testArrivingReadsTheRelationsWrittenTowardsASymbol(): void
    {
        $showCalls = new EdgeDatum('show>total', ['methodCall'], [], 'App\Http\Controller::show', 'App\Domain\Invoice::total');
        $storeCalls = new EdgeDatum('store>total', ['methodCall'], [], 'App\Http\Controller::store', 'App\Domain\Invoice::total');
        $graph = new ElementGraph(
            [],
            ['App\Http\Controller::show' => [$showCalls], 'App\Http\Controller::store' => [$storeCalls]],
            ['App\Domain\Invoice::total' => [$showCalls, $storeCalls]],
        );

        self::assertSame([$showCalls, $storeCalls], $graph->arriving('App\Domain\Invoice::total'));
    }

    public function testArrivingReadsNothingTowardsASymbolNothingIsWrittenTowards(): void
    {
        $showCalls = new EdgeDatum('show>total', ['methodCall'], [], 'App\Http\Controller::show', 'App\Domain\Invoice::total');
        $graph = new ElementGraph([], ['App\Http\Controller::show' => [$showCalls]], ['App\Domain\Invoice::total' => [$showCalls]]);

        self::assertSame([], $graph->arriving('App\Http\Controller::show'));
    }

    public function testBetweenReadsTheRelationsJoiningASetOfSymbols(): void
    {
        $showCalls = new EdgeDatum('show>total', ['methodCall'], [], 'App\Http\Controller::show', 'App\Domain\Invoice::total');
        $totalCalls = new EdgeDatum('total>get', ['methodCall'], [], 'App\Domain\Invoice::total', 'App\Cache\Store::get');
        $graph = new ElementGraph(
            [],
            ['App\Http\Controller::show' => [$showCalls], 'App\Domain\Invoice::total' => [$totalCalls]],
            ['App\Domain\Invoice::total' => [$showCalls], 'App\Cache\Store::get' => [$totalCalls]],
        );

        self::assertSame(
            [$showCalls, $totalCalls],
            $graph->between(['App\Http\Controller::show' => true, 'App\Domain\Invoice::total' => true, 'App\Cache\Store::get' => true]),
        );
    }

    public function testBetweenLeavesOutARelationWithOnlyOneEndInTheSet(): void
    {
        $showCalls = new EdgeDatum('show>total', ['methodCall'], [], 'App\Http\Controller::show', 'App\Domain\Invoice::total');
        $totalCalls = new EdgeDatum('total>get', ['methodCall'], [], 'App\Domain\Invoice::total', 'App\Cache\Store::get');
        $graph = new ElementGraph(
            [],
            ['App\Http\Controller::show' => [$showCalls], 'App\Domain\Invoice::total' => [$totalCalls]],
            ['App\Domain\Invoice::total' => [$showCalls], 'App\Cache\Store::get' => [$totalCalls]],
        );

        self::assertSame([$showCalls], $graph->between(['App\Http\Controller::show' => true, 'App\Domain\Invoice::total' => true]));
    }

    public function testBetweenJoinsNothingWhenNothingIsWanted(): void
    {
        $showCalls = new EdgeDatum('show>total', ['methodCall'], [], 'App\Http\Controller::show', 'App\Domain\Invoice::total');
        $graph = new ElementGraph([], ['App\Http\Controller::show' => [$showCalls]], ['App\Domain\Invoice::total' => [$showCalls]]);

        self::assertSame([], $graph->between([]));
    }
}
