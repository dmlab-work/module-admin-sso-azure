<?php
/**
 * Copyright © DMLab. All rights reserved.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use DmLab\AdminSsoAzure\Model\EntraPreset;
use PHPUnit\Framework\TestCase;

/**
 * Asserts di.xml wires the Entra preset into admin-sso's open/closed seam so the
 * capability core indexes it under the `azure` code.
 */
class PresetRegistrationTest extends TestCase
{
    private const REGISTRY = 'DmLab\\AdminSso\\Model\\PresetRegistry';

    public function testDiXmlRegistersEntraPresetIntoPresetRegistry(): void
    {
        $diXml = dirname(__DIR__, 2) . '/etc/di.xml';
        self::assertFileExists($diXml);

        $dom = new \DOMDocument();
        self::assertTrue($dom->load($diXml));

        $item = $this->findPresetItem($dom);

        self::assertNotNull($item, 'No "azure" preset item registered on the PresetRegistry type.');
        self::assertSame('azure', $item->getAttribute('name'));
        self::assertSame(EntraPreset::class, ltrim(trim($item->textContent), '\\'));
    }

    private function findPresetItem(\DOMDocument $dom): ?\DOMElement
    {
        foreach ($dom->getElementsByTagName('type') as $type) {
            if (ltrim($type->getAttribute('name'), '\\') !== self::REGISTRY) {
                continue;
            }
            foreach ($type->getElementsByTagName('argument') as $argument) {
                if ($argument->getAttribute('name') !== 'presets') {
                    continue;
                }
                foreach ($argument->getElementsByTagName('item') as $item) {
                    if ($item->getAttribute('name') === 'azure') {
                        return $item;
                    }
                }
            }
        }

        return null;
    }
}
