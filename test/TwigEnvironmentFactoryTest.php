<?php

/**
 * @see       https://github.com/mezzio/mezzio-twigrenderer for the canonical source repository
 * @copyright https://github.com/mezzio/mezzio-twigrenderer/blob/master/COPYRIGHT.md
 * @license   https://github.com/mezzio/mezzio-twigrenderer/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace MezzioTest\Twig;

use DateTimeZone;
use Mezzio\Helper\ServerUrlHelper;
use Mezzio\Helper\UrlHelper;
use Mezzio\Twig\Exception\InvalidConfigException;
use Mezzio\Twig\Exception\InvalidExtensionException;
use Mezzio\Twig\Exception\InvalidRuntimeLoaderException;
use Mezzio\Twig\TwigEnvironmentFactory;
use Mezzio\Twig\TwigExtension;
use Mezzio\Twig\TwigExtensionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionException;
use ReflectionProperty;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\EscaperExtension;
use Twig\Extension\OptimizerExtension;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

class TwigEnvironmentFactoryTest extends TestCase
{
    /** @var MockObject<ContainerInterface> */
    private $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    /**
     * @throws LoaderError
     */
    public function testCallingFactoryWithNoConfigReturnsTwigEnvironmentInstance(): TwigEnvironment
    {
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturn(false);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertInstanceOf(TwigEnvironment::class, $environment);

        return $environment;
    }

    /**
     * @throws LoaderError
     */
    public function testUsesDebugConfiguration(): void
    {
        $config = ['debug' => true];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertTrue($environment->isDebug());
        $this->assertFalse($environment->getCache());
        $this->assertTrue($environment->isStrictVariables());
        $this->assertTrue($environment->isAutoReload());
    }

    /**
     * @depends testCallingFactoryWithNoConfigReturnsTwigEnvironmentInstance
     */
    public function testDebugDisabledSetsUpEnvironmentForProduction(TwigEnvironment $environment): void
    {
        $this->assertFalse($environment->isDebug());
        $this->assertFalse($environment->isStrictVariables());
        $this->assertFalse($environment->isAutoReload());
    }

    /**
     * @throws LoaderError
     */
    public function testCanSpecifyCacheDirectoryViaConfiguration(): void
    {
        $config = ['templates' => ['cache_dir' => __DIR__]];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertEquals($config['templates']['cache_dir'], $environment->getCache());
    }

    /**
     * @throws LoaderError
     */
    public function testAddsTwigExtensionIfRouterIsInContainer(): void
    {
        $twigExtensionFactory = new TwigExtensionFactory();
        $serverUrlHelper      = $this->createMock(ServerUrlHelper::class);
        $urlHelper            = $this->createMock(UrlHelper::class);

        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', false], [TwigExtension::class, true], [ServerUrlHelper::class, true], [UrlHelper::class, true]]
        );

        $container = $this->container;
        $this->container->expects(self::atLeastOnce())->method('get')->withAnyParameters()->willReturnCallback(
            function (string $id) use ($twigExtensionFactory, $serverUrlHelper, $urlHelper, $container) {
                switch ($id) {
                    case TwigExtension::class:
                        return $twigExtensionFactory($container);
                    case ServerUrlHelper::class:
                        return $serverUrlHelper;
                    case UrlHelper::class:
                        return $urlHelper;
                }
                return null;
            }
        );

        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertTrue($environment->hasExtension(TwigExtension::class));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function invalidExtensions(): array
    {
        return [
            'null'                  => [null],
            'true'                  => [true],
            'false'                 => [false],
            'zero'                  => [0],
            'int'                   => [1],
            'zero-float'            => [0.0],
            'float'                 => [1.1],
            'non-service-string'    => ['not-an-extension'],
            'array'                 => [['not-an-extension']],
            'non-extensions-object' => [(object) ['extension' => 'not-an-extension']],
        ];
    }

    /**
     * @dataProvider invalidExtensions
     * @param mixed $extension
     * @throws LoaderError
     */
    public function testRaisesExceptionForInvalidExtensions($extension): void
    {
        $config = [
            'templates' => [],
            'twig'      => [
                'extensions' => [$extension],
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false], [$extension, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);

        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidExtensionException::class);
        $factory($this->container);
    }

    /**
     * @return array<string, mixed>
     */
    public function invalidConfiguration(): array
    {
        //                        [Config value, Type]
        return [
            'true'             => [true, 'boolean'],
            'false'            => [false, 'boolean'],
            'zero'             => [0, 'integer'],
            'int'              => [1, 'integer'],
            'zero-float'       => [0.0, 'double'],
            'float'            => [1.1, 'double'],
            'string'           => ['not-configuration', 'string'],
            'non-array-object' => [(object) ['not' => 'configuration'], 'stdClass'],
        ];
    }

    /**
     * @dataProvider invalidConfiguration
     * @param mixed $config
     * @throws LoaderError
     */
    public function testRaisesExceptionForInvalidConfigService($config, string $contains): void
    {
        $this->container->expects(self::atLeastOnce())->method('has')->with('config')->willReturn(true);
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($contains);
        $factory($this->container);
    }

    /**
     * @throws LoaderError
     */
    public function testUsesTimezoneConfiguration(): void
    {
        $tz     = DateTimeZone::listIdentifiers()[0];
        $config = [
            'twig' => [
                'timezone' => $tz,
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);
        /** @var CoreExtension $extension */
        $extension = $environment->getExtension(CoreExtension::class);
        $fetchedTz = $extension->getTimezone();
        $this->assertEquals(new DateTimeZone($tz), $fetchedTz);
    }

    /**
     * @throws LoaderError
     */
    public function testRaisesExceptionForInvalidTimezone(): void
    {
        $tz     = 'Luna/Copernicus_Crater';
        $config = [
            'twig' => [
                'timezone' => $tz,
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->with('config')->willReturn(true);
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidConfigException::class);
        $factory($this->container);
    }

    /**
     * @throws LoaderError
     */
    public function testRaisesExceptionForNonStringTimezone(): void
    {
        $config = [
            'twig' => [
                'timezone' => new DateTimeZone('UTC'),
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->with('config')->willReturn(true);
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('"timezone" configuration value must be a string');

        $factory($this->container);
    }

    /**
     * @return array<string, mixed>
     */
    public function invalidRuntimeLoaders(): array
    {
        return [
            'null'                  => [null],
            'true'                  => [true],
            'false'                 => [false],
            'zero'                  => [0],
            'int'                   => [1],
            'zero-float'            => [0.0],
            'float'                 => [1.1],
            'non-service-string'    => ['not-an-runtime-loader'],
            'array'                 => [['not-an-runtime-loader']],
            'non-extensions-object' => [(object) ['extension' => 'not-an-runtime-loader']],
        ];
    }

    /**
     * @dataProvider invalidRuntimeLoaders
     * @param mixed $runtimeLoader
     * @throws LoaderError
     */
    public function testRaisesExceptionForInvalidRuntimeLoaders($runtimeLoader): void
    {
        $config = [
            'templates' => [],
            'twig'      => [
                'runtime_loaders' => [$runtimeLoader],
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false], [$runtimeLoader, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);

        $factory = new TwigEnvironmentFactory();

        $this->expectException(InvalidRuntimeLoaderException::class);
        $factory($this->container);
    }

    /**
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testInjectsCustomRuntimeLoadersIntoTwigEnvironment(): void
    {
        $fooRuntime = $this->createMock(RuntimeLoaderInterface::class);
        $fooRuntime->expects(self::atLeastOnce())->method('load')->withAnyParameters()->willReturnMap(
            [['Test\Runtime\FooRuntime', 'foo-runtime'], ['Test\Runtime\BarRuntime', null]]
        );

        $barRuntime = $this->createMock(RuntimeLoaderInterface::class);
        $barRuntime->expects(self::atLeastOnce())->method('load')->withAnyParameters()->willReturnMap(
            [['Test\Runtime\BarRuntime', 'bar-runtime'], ['Test\Runtime\FooRuntime', null]]
        );

        $config = [
            'templates' => [],
            'twig'      => [
                'runtime_loaders' => [
                    $fooRuntime,
                    'Test\Runtime\BarRuntimeLoader',
                ],
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false], ['Test\Runtime\BarRuntimeLoader', true]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->withAnyParameters()->willReturnMap([
            ['config', $config],
            ['Test\Runtime\BarRuntimeLoader', $barRuntime],
        ]);

        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertInstanceOf(TwigEnvironment::class, $environment);
        $this->assertEquals('foo-runtime', $environment->getRuntime('Test\Runtime\FooRuntime'));
        $this->assertEquals('bar-runtime', $environment->getRuntime('Test\Runtime\BarRuntime'));
    }

    /**
     * @throws LoaderError|ReflectionException
     */
    public function testUsesOptimizationsConfiguration(): void
    {
        $config = [
            'twig' => [
                'optimizations' => 0,
            ],
        ];
        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $extension = $environment->getExtension(OptimizerExtension::class);
        $property  = new ReflectionProperty($extension, 'optimizers');
        $property->setAccessible(true);

        $this->assertSame(0, $property->getValue($extension));
    }

    /**
     * @throws LoaderError
     */
    public function testUsesAutoescapeConfiguration(): void
    {
        $config = [
            'twig' => [
                'autoescape' => false,
            ],
        ];

        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::atLeastOnce())->method('get')->with('config')->willReturn($config);
        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);
        /** @var EscaperExtension $extension */
        $extension = $environment->getExtension(EscaperExtension::class);
        $this->assertFalse($extension->getDefaultStrategy('template::name'));
    }

    /**
     * @throws LoaderError
     */
    public function testAutoReloadIgnoreDebugConfiguration(): void
    {
        $config = [
            'debug' => true,
            'twig'  => [
                'auto_reload' => false,
            ],
        ];

        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::once())->method('get')->with('config')->willReturn($config);

        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertFalse($environment->isAutoReload());
        $this->assertTrue($environment->isDebug());
    }

    /**
     * @throws LoaderError
     */
    public function testAutoReloadUsesConfiguration(): void
    {
        $config = [
            'debug' => false,
            'twig'  => [
                'auto_reload' => true,
            ],
        ];

        $this->container->expects(self::atLeastOnce())->method('has')->withAnyParameters()->willReturnMap(
            [['config', true], [TwigExtension::class, false]]
        );
        $this->container->expects(self::once())->method('get')->with('config')->willReturn($config);

        $factory     = new TwigEnvironmentFactory();
        $environment = $factory($this->container);

        $this->assertTrue($environment->isAutoReload());
        $this->assertFalse($environment->isDebug());
    }
}
