<?php

declare(strict_types=1);

namespace Tests\Fixture\Config;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * A parsed command line, built from the arguments a test wants to pass.
 *
 * Reading the command line means asking a console input which options were
 * actually typed, and that only works against the real definition of the command.
 * Declaring it here keeps every test that exercises the command line honest about
 * which options exist, without each of them restating twelve declarations.
 */
final class ConsoleInput
{
    /**
     * Builds a console input over peq's own command line definition.
     *
     * @param array<string, mixed> $typed What the user typed, as ArrayInput spells it
     *
     * @return InputInterface The parsed command line
     */
    public static function of(array $typed): InputInterface
    {
        return new ArrayInput($typed, self::definition());
    }

    /**
     * Declares the arguments and options peq's inspect command accepts.
     *
     * @return InputDefinition The command line definition
     */
    public static function definition(): InputDefinition
    {
        return new InputDefinition([
            new InputArgument('target', InputArgument::OPTIONAL),
            new InputArgument('path', InputArgument::OPTIONAL),
            new InputOption('config', null, InputOption::VALUE_REQUIRED),
            new InputOption('direction', 'D', InputOption::VALUE_REQUIRED),
            new InputOption('level', 'L', InputOption::VALUE_REQUIRED),
            new InputOption('reverse', 'R', InputOption::VALUE_NONE),
            new InputOption('include', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '', []),
            new InputOption('exclude', 'E', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, '', []),
            new InputOption('type', null, InputOption::VALUE_REQUIRED),
            new InputOption('debug-depth', null, InputOption::VALUE_REQUIRED),
            new InputOption('debug-seed', null, InputOption::VALUE_REQUIRED),
            new InputOption('memory-limit', null, InputOption::VALUE_REQUIRED),
        ]);
    }
}
