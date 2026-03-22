<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\Graph;

use App\Analyzer\Graph\IdentifierAssert;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class IdentifierAssertTest extends TestCase
{
    use IdentifierAssert;

    #[Test]
    #[DoesNotPerformAssertions]
    public function testAssertValidIdentifierWithValidValue(): void
    {
        self::assertIdentifier('MyClass');
        self::assertIdentifier('_private');
        self::assertIdentifier('test123');
        self::assertIdentifier('ClassName');
        self::assertIdentifier('methodName');
        self::assertIdentifier('CONSTANT_NAME');
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public function testAssertValidNamespaceWithValidValue(): void
    {
        self::assertNamespace('App');
        self::assertNamespace('App\Service');
        self::assertNamespace('Vendor\Package\SubPackage');
        self::assertNamespace('My\Very\Long\Namespace\Path');
        self::assertNamespace('_Private\Namespace');
    }

    #[Test]
    public function testAssertValidIdentifierWithInvalidValue(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertIdentifier('');
    }

    #[Test]
    public function testAssertValidIdentifierWithInvalidValueStartsWithNumber(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertIdentifier('123invalid');
    }

    #[Test]
    public function testAssertValidNamespaceWithInvalidValue(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertNamespace('');
    }

    #[Test]
    public function testAssertValidNamespaceWithInvalidValueStartsWithNumber(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertNamespace('123\Invalid');
    }

    #[Test]
    public function testAssertValidIdentifierWithInvalidCharacters(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertIdentifier('invalid-char');
    }

    #[Test]
    public function testAssertValidIdentifierWithDot(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertIdentifier('invalid.char');
    }

    #[Test]
    public function testAssertValidNamespaceWithInvalidCharacters(): void
    {
        $this->expectException(\AssertionError::class);
        self::assertNamespace('Invalid-Namespace');
    }
}
