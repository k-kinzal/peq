<?php

declare(strict_types=1);

namespace Tests\Fixture\Source;

interface InheritancePayable
{
    public function amount(): int;
}

interface InheritanceRefundable extends InheritancePayable
{
    public function refund(): void;
}

abstract class InheritanceDocument
{
    abstract public function title(): string;
}

trait InheritanceTimestamped
{
    public function createdAt(): int
    {
        return 0;
    }
}

trait InheritanceAuditable
{
    use InheritanceTimestamped;

    public function auditedBy(): string
    {
        return '';
    }
}

final class InheritanceInvoice extends InheritanceDocument implements InheritanceRefundable
{
    use InheritanceAuditable;

    public function title(): string
    {
        return 'invoice';
    }

    public function amount(): int
    {
        return 0;
    }

    public function refund(): void {}
}

enum InheritanceStatus: string implements InheritancePayable
{
    case Open = 'open';
    case Paid = 'paid';

    public function amount(): int
    {
        return 0;
    }
}
