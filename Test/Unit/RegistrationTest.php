<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use Magento\Framework\Component\ComponentRegistrar;
use PHPUnit\Framework\TestCase;

class RegistrationTest extends TestCase
{
    public function testModuleIsRegistered(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);

        self::assertArrayHasKey('DmLab_AdminSsoAzure', $paths);
    }

    public function testRegisteredPathPointsAtThisModule(): void
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);
        $path = $paths['DmLab_AdminSsoAzure'] ?? null;

        self::assertNotNull($path);
        self::assertDirectoryExists($path);
        self::assertFileExists($path . '/etc/module.xml');
    }

    public function testModuleSequencesAfterAdminSso(): void
    {
        self::assertContains('DmLab_AdminSso', $this->sequencedModules());
    }

    public function testModuleSequencesAfterSsoCore(): void
    {
        self::assertContains('DmLab_SsoCore', $this->sequencedModules());
    }

    /**
     * @return string[]
     */
    private function sequencedModules(): array
    {
        $paths = (new ComponentRegistrar())->getPaths(ComponentRegistrar::MODULE);
        $moduleXml = ($paths['DmLab_AdminSsoAzure'] ?? '') . '/etc/module.xml';

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($moduleXml));

        $sequenced = [];
        foreach ($dom->getElementsByTagName('sequence') as $sequence) {
            foreach ($sequence->getElementsByTagName('module') as $module) {
                $sequenced[] = $module->getAttribute('name');
            }
        }

        return $sequenced;
    }
}
