<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Fixture;

#[\Attribute]
class MyAttribute {}

interface MyInterface {}

trait MyTrait
{
    public function traitMethod(): void {}
}

enum MyEnum: string
{
    case A = 'a';
    case B = 'b';
}

#[MyAttribute]
class ComplexClass implements MyInterface
{
    use MyTrait;

    #[MyAttribute]
    public const MY_CONST = 'value';

    #[MyAttribute]
    public int $myProp;

    public MyEnum $enumProp;

    public function __construct(
        #[MyAttribute]
        public readonly string $promotedProp
    ) {
        $this->myProp = 0;
        $this->enumProp = MyEnum::A;
    }

    #[MyAttribute]
    public function complexMethod(int $param1, MyInterface $param2): ?string
    {
        try {
            if ($param2 instanceof ComplexClass) {
                return 'complex';
            }
            if ($param1 > 0) {
                throw new \RuntimeException();
            }

            throw new \Exception();
        } catch (\RuntimeException $e) {
            // catch
        } catch (\Exception $e) {
            // catch
        }

        $len = strlen('test'); // function call

        return null;
    }
}
