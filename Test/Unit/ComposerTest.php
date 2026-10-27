<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComposerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        $json = file_get_contents(dirname(__DIR__, 2) . '/composer.json');
        self::assertIsString($json);

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->composer()['version'] ?? '');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function requiredPackages(): array
    {
        return [
            'sso-core' => ['dmlab/module-sso-core', '^0.2.2'],
            'admin-sso' => ['dmlab/module-admin-sso', '^0.2.2'],
            'module-config' => ['magento/module-config', '*'],
        ];
    }

    #[DataProvider('requiredPackages')]
    public function testPackageIsRequired(string $package, string $constraint): void
    {
        self::assertSame($constraint, $this->composer()['require'][$package] ?? null);
    }
}
