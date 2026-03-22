<?php

declare(strict_types=1);

namespace Tests\Unit\Analyzer\PhpStanAnalyzer\Collector\Fixture;

function usage_target_func(): void {}

class UsageDep
{
    public static int $staticCount = 0;
    public int $instanceProp = 0;

    public function depMethod(): void {}
}

class UsageProcessorFixture
{
    public int $myProp = 0;

    public function helperMethod(): int
    {
        return 1;
    }

    public function testMethod(): void
    {
        // FunctionCallProcessor
        usage_target_func();

        // MethodCallProcessor: $this->method()
        $this->helperMethod();

        // PropertyAccessProcessor: $this->prop
        $x = $this->myProp;

        // StaticPropertyAccessProcessor: Dep::$prop
        $z = UsageDep::$staticCount;

        // Non-detection: $obj->method() and $obj->prop
        $dep = new UsageDep();
        $dep->depMethod();
        $w = $dep->instanceProp;
    }
}
