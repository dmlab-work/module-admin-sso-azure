<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use PHPUnit\Framework\TestCase;

class PackagingTest extends TestCase
{
    private function ciWorkflow(): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/ci.yml');
    }

    public function testCiMatrixCoversPhp82To85(): void
    {
        self::assertStringContainsString("php: ['8.2', '8.3', '8.4', '8.5']", $this->ciWorkflow());
    }

    public function testCiDisablesPackagistNotifyBeforeInstall(): void
    {
        $ci = $this->ciWorkflow();
        $configure = strpos($ci, 'name: Configure Magento packages');
        $notify = strpos($ci, 'composer config notify-on-install false');
        $install = strpos($ci, 'ramsey/composer-install');

        self::assertNotFalse($configure);
        self::assertNotFalse($notify);
        self::assertNotFalse($install);
        self::assertGreaterThan($configure, $notify);
        self::assertLessThan($install, $notify);
    }

    public function testReadmeStatesRequirementsInstallAndPairing(): void
    {
        $readme = $this->readme();

        self::assertStringContainsString('- Magento 2.4.7+ · PHP 8.2–8.5', $readme);
        self::assertStringContainsString(
            "## Install\n\n```bash\ncomposer require dmlab/module-admin-sso-azure",
            $readme
        );
        self::assertStringContainsString(
            'Pairs with [admin-scim-azure](https://github.com/dmlab-work/module-admin-scim-azure)',
            $readme
        );
    }

    public function testReadmeScreenshotExists(): void
    {
        self::assertStringContainsString('](.github/images/config.png)', $this->readme());
        self::assertFileExists(dirname(__DIR__, 2) . '/.github/images/config.png');
    }

    public function testReadmeSectionOrder(): void
    {
        $readme = $this->readme();
        $positions = array_map(
            static fn (string $heading): int|false => strpos($readme, "\n" . $heading . "\n"),
            [
                '## Install', '## Register the Entra app', '### Users without an email', '### Groups overage',
                '## Requirements', '## Architecture', '## License',
            ]
        );

        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);
    }

    public function testVersionIs022AndFirstInChangelog(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode((string)file_get_contents($root . '/composer.json'), true);
        self::assertSame('0.2.2', $composer['version']);

        preg_match('/^## (.+)$/m', (string)file_get_contents($root . '/CHANGELOG.md'), $m);
        self::assertSame('0.2.2', $m[1] ?? null);
    }

    private function readme(): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2) . '/README.md');
    }
}
