<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminSsoAzure\Test\Unit\Model;

use DmLab\AdminSsoAzure\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * Build a Config over a scope stub returning the given stored value.
     */
    private function config(mixed $storedValue): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($storedValue);

        return new Config($scopeConfig);
    }

    public function testReturnsTrimmedTenant(): void
    {
        self::assertSame(
            '11111111-2222-3333-4444-555555555555',
            $this->config('  11111111-2222-3333-4444-555555555555  ')->getTenant()
        );
    }

    public function testUnsetTenantIsNull(): void
    {
        self::assertNull($this->config(null)->getTenant());
    }

    public function testWhitespaceOnlyTenantNormalizesToNull(): void
    {
        self::assertNull($this->config("  \t ")->getTenant());
    }

    public function testNonStringTenantIsNull(): void
    {
        self::assertNull($this->config(['unexpected'])->getTenant());
    }
}
