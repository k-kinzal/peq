<?php

declare(strict_types=1);

namespace Tests\Unit\Gql\Lexing;

use App\Gql\Lexing\TokenKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TokenKind::class)]
#[Small]
final class TokenKindTest extends TestCase
{
    public function testThePiecesOfAQueryAreTheOnlyKindsThereAre(): void
    {
        self::assertSame(
            [
                TokenKind::Name,
                TokenKind::QuotedName,
                TokenKind::Integer,
                TokenKind::Decimal,
                TokenKind::Approximate,
                TokenKind::Text,
                TokenKind::Symbol,
                TokenKind::End,
            ],
            TokenKind::cases(),
        );
    }

    public function testAKeywordIsNotAKindOfItsOwn(): void
    {
        $named = array_map(static fn (TokenKind $kind): string => $kind->name, TokenKind::cases());

        self::assertNotContains('Keyword', $named);
    }

    public function testANameInBackticksIsAKindOfItsOwnSoThatItIsNeverAKeyword(): void
    {
        self::assertNotSame(TokenKind::Name, TokenKind::QuotedName);
    }
}
