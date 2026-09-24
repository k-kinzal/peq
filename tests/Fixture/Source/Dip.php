<?php

declare(strict_types=1);

namespace Tests\Fixture\Source\Dip;

use Attribute;
use DateTimeImmutable;
use PDO;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Trace
{
    public function __construct(public string $operation) {}
}

interface Port
{
    public function execute(): void;

    public function unused(): void;
}

interface Specialized extends Port {}

class BaseService
{
    public function __construct(private PDO $pdo) {}

    #[Trace('query'), Trace(operation: 'audit')]
    public function execute(): void
    {
        $this->pdo->query('SELECT 1');
    }

    public function unused(): void {}
}

final class Service extends BaseService implements Specialized {}

final class OtherService implements Port
{
    public function execute(): void {}

    public function unused(): void {}
}

final class Controller
{
    public function __construct(private Specialized $port) {}

    public function action(): void
    {
        $this->port->execute();
        $this->port->execute();
    }

    public function unrelated(): void
    {
        new DateTimeImmutable();
    }
}

function invoke(#[Trace('parameter')] Port $port): void
{
    $port->execute();
}
