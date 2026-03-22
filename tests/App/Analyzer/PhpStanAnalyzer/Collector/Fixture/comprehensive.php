<?php

declare(strict_types=1);

namespace Tests\App\Analyzer\PhpStanAnalyzer\Collector\Fixture;

#[\Attribute]
class MyAttribute {}

interface MyInterface {}

trait MyTrait {}

enum MyEnum: string
{
    case A = 'a';
}

#[MyAttribute]
class ComprehensiveClass implements MyInterface
{
    use MyTrait;

    #[MyAttribute]
    public const MY_CONST = 'value';

    #[MyAttribute]
    public int $myProp = 0;

    public function __construct(
        #[MyAttribute]
        public readonly string $promotedProp
    ) {}

    #[MyAttribute]
    public function myMethod(): void {}
}
