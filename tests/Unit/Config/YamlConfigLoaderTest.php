<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Config\ConfigException;
use App\Config\YamlConfigLoader;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class YamlConfigLoaderTest extends TestCase
{
    #[Test]
    public function testReadReturnsConfigFromYamlFile(): void
    {
        $yaml = <<<'YAML'
            basePath: /project/path
            direction: uses
            level: 3
            includes:
              - src/**/*.php
              - lib/**/*.php
            excludes:
              - vendor/**
            YAML;

        $root = vfsStream::setup('config');
        vfsStream::newFile('.peq.yaml')
            ->at($root)
            ->setContent($yaml)
        ;

        $reader = new YamlConfigLoader(vfsStream::url('config/.peq.yaml'));
        $config = $reader->read();

        self::assertSame('/project/path', $config['basePath']);
        self::assertSame('uses', $config['direction']);
        self::assertSame(3, $config['level']);
        self::assertSame(['src/**/*.php', 'lib/**/*.php'], $config['includes']);
        self::assertSame(['vendor/**'], $config['excludes']);
    }

    #[Test]
    public function testReadReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $reader = new YamlConfigLoader(vfsStream::url('config/nonexistent.yaml'));
        $config = $reader->read();

        self::assertSame([], $config);
    }

    #[Test]
    public function testReadThrowsExceptionWhenYamlParsingFails(): void
    {
        $invalidYaml = "basePath: /path\n  invalid: indentation\n bad: yaml";

        $root = vfsStream::setup('config');
        vfsStream::newFile('invalid.yaml')
            ->at($root)
            ->setContent($invalidYaml)
        ;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Failed to parse YAML/');

        $reader = new YamlConfigLoader(vfsStream::url('config/invalid.yaml'));
        $reader->read();
    }

    #[Test]
    public function testReadThrowsExceptionWhenContentIsNotArray(): void
    {
        $scalarYaml = 'just a string';

        $root = vfsStream::setup('config');
        vfsStream::newFile('scalar.yaml')
            ->at($root)
            ->setContent($scalarYaml)
        ;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Invalid configuration format/');

        $reader = new YamlConfigLoader(vfsStream::url('config/scalar.yaml'));
        $reader->read();
    }

    #[Test]
    public function testReadThrowsExceptionWhenFileCannotBeRead(): void
    {
        $root = vfsStream::setup('config');
        $file = vfsStream::newFile('unreadable.yaml', 0o000)
            ->at($root)
            ->setContent('foo: bar')
        ;

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessageMatches('/Failed to read configuration file/');

        $reader = new YamlConfigLoader($file->url());
        $reader->read();
    }
}
