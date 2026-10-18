<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Asserts i18n/en_US.csv covers system.xml phrases and every PHP/XML file carries the OSL-3.0 header.
 */
class ReleaseHygieneTest extends TestCase
{
    private const OSL_LINE = ' * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.';

    /**
     * @return array<string, string>
     */
    private function translations(): array
    {
        $handle = fopen(dirname(__DIR__, 2) . '/i18n/en_US.csv', 'r');
        self::assertNotFalse($handle);
        $phrases = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            self::assertCount(2, $row);
            $phrases[$row[0]] = $row[1];
        }
        fclose($handle);

        return $phrases;
    }

    /**
     * @return string[]
     */
    private function systemXmlPhrases(): array
    {
        $dom = new \DOMDocument();
        self::assertTrue($dom->load(dirname(__DIR__, 2) . '/etc/adminhtml/system.xml'));
        $xpath = new \DOMXPath($dom);

        $phrases = [];
        foreach ($xpath->query('//*[@translate]') as $element) {
            foreach (preg_split('/\s+/', trim($element->getAttribute('translate'))) as $child) {
                foreach ($xpath->query($child, $element) as $node) {
                    $phrases[] = trim($node->textContent);
                }
            }
        }

        return $phrases;
    }

    public function testEverySystemXmlLabelAndCommentIsTranslated(): void
    {
        $translations = $this->translations();
        $phrases = $this->systemXmlPhrases();

        self::assertCount(4, $phrases);
        self::assertContains('Microsoft Entra ID', $phrases);
        self::assertContains('Tenant', $phrases);
        foreach ($phrases as $phrase) {
            self::assertArrayHasKey($phrase, $translations);
        }
    }

    public function testEveryPhpAndXmlFileCarriesOslHeader(): void
    {
        $root = dirname(__DIR__, 2);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                static fn (\SplFileInfo $file): bool => !in_array(
                    $file->getPathname(),
                    [$root . '/.git', $root . '/vendor', $root . '/Test/Unit/.phpunit.cache'],
                    true
                )
            )
        );

        $checked = 0;
        foreach ($files as $file) {
            if (!in_array($file->getExtension(), ['php', 'xml'], true)) {
                continue;
            }
            $lines = explode("\n", (string)file_get_contents($file->getPathname()));
            $copyright = array_search(' * Copyright © DMLab. All rights reserved.', $lines, true);
            self::assertIsInt($copyright, $file->getPathname() . ' lacks the copyright header');
            self::assertSame(self::OSL_LINE, $lines[$copyright + 1] ?? null, $file->getPathname());
            $checked++;
        }

        self::assertGreaterThanOrEqual(19, $checked);
    }
}
