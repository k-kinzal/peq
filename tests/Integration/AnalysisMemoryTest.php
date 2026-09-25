<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Analyzer\PhpStanAnalyzer\WorkingDirectory;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[CoversNothing]
#[Large]
final class AnalysisMemoryTest extends TestCase
{
    /**
     * @throws JsonException If the isolated analyzer does not return a JSON result
     */
    public function testColdWarmAndEditedAnalysisFitTheSameMemoryBudget(): void
    {
        $directory = WorkingDirectory::at(sys_get_temp_dir().'/peq-memory-'.uniqid());
        $sources = WorkingDirectory::at($directory->path.'/src');
        $source = "<?php\nclass Service%d {\n    public function run(int \$value): int {\n"
            .str_repeat("        \$value = \$this->step(\$value);\n", 90)
            ."        return \$value;\n    }\n    public function step(int \$value): int { return \$value + 1; }\n}\n";
        array_map(static fn (int $i): string => $sources->write('Service'.$i.'.php', sprintf($source, $i)), range(1, 300));
        $program = <<<'PHP'
            require $argv[1];
            $cache = new \App\Analyzer\PhaseCache(new \App\Analyzer\CacheStorage($argv[3], 'memory-test'));
            $graph = (new \App\Analyzer\NativeAnalyzer\NativeAnalyzer(cache: $cache))->analyze($argv[2]);
            echo json_encode([
                count($graph->nodes()),
                count($graph->forwardEdges()),
                \App\Analyzer\Graph\GraphSnapshot::of($graph)->fingerprint(),
            ], JSON_THROW_ON_ERROR);
            PHP;
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=256M', '-r', $program, dirname(__DIR__, 2).'/vendor/autoload.php', $sources->path, $directory->path.'/cache'], env: ['XDEBUG_MODE' => 'off']);

        try {
            $process->mustRun();
            $cold = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $process->mustRun();
            $warm = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $sources->write('Service1.php', sprintf(str_replace('step($value)', 'step(42)', $source), 1));
            $process->mustRun();
            $edited = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $process->mustRun();
            $editedWarm = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

            self::assertIsArray($cold);
            self::assertIsArray($edited);
            self::assertSame([900, 27600], array_slice($cold, 0, 2));
            self::assertSame([900, 27600], array_slice($edited, 0, 2));
            self::assertSame($cold, $warm);
            self::assertSame($edited, $editedWarm);
            self::assertNotSame($cold, $edited);
        } finally {
            $directory->delete();
        }
    }
}
