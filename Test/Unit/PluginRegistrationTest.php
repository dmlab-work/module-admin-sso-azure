<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use DmLab\AdminSsoAzure\Plugin\EntraEmailFallback;
use DmLab\AdminSsoAzure\Plugin\EntraGroupsOverage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Asserts di.xml declares the Entra email fallback on sso-core's IdentityFactory
 * and that `beforeCreate` matches `IdentityFactory::create`.
 */
class PluginRegistrationTest extends TestCase
{
    private const SUBJECT = 'DmLab\\SsoCore\\Model\\Oidc\\IdentityFactory';

    public function testDiXmlDeclaresEmailFallbackOnIdentityFactory(): void
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->load(dirname(__DIR__, 2) . '/etc/di.xml'));

        $declared = [];
        foreach ($dom->getElementsByTagName('type') as $type) {
            if (ltrim($type->getAttribute('name'), '\\') !== self::SUBJECT) {
                continue;
            }
            foreach ($type->getElementsByTagName('plugin') as $plugin) {
                $declared[] = ltrim($plugin->getAttribute('type'), '\\');
            }
        }

        self::assertContains(EntraEmailFallback::class, $declared);
        self::assertContains(EntraGroupsOverage::class, $declared);
    }

    /**
     * @return array<string,array{0:class-string}>
     */
    public static function plugins(): array
    {
        return ['email fallback' => [EntraEmailFallback::class], 'groups overage' => [EntraGroupsOverage::class]];
    }

    #[DataProvider('plugins')]
    public function testBeforeCreateMatchesSubjectSignature(string $pluginClass): void
    {
        $signature = static fn (\ReflectionMethod $method): array => array_map(
            static fn (\ReflectionParameter $p): string => $p->getName() . ':' . $p->getType(),
            $method->getParameters()
        );

        $plugin = $signature(new \ReflectionMethod($pluginClass, 'beforeCreate'));

        self::assertSame(
            $signature(new \ReflectionMethod(self::SUBJECT, 'create')),
            array_slice($plugin, 1)
        );
    }
}
