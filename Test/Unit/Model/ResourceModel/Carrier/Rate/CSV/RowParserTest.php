<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace DHLParcel\Shipping\Test\Unit\Model\ResourceModel\Carrier\Rate\CSV;

use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\CSV\RowParser;
use DHLParcel\Shipping\Model\ResourceModel\Carrier\Rate\LocationDirectory;
use PHPUnit\Framework\TestCase;

class RowParserTest extends TestCase
{
    /**
     * @return void
     */
    public function testParseDecimalValueReturnsFloat()
    {
        $locationDirectory = $this->createStub(LocationDirectory::class);

        $rowParser = new RowParser($locationDirectory);

        $method = new \ReflectionMethod(RowParser::class, 'parseDecimalValue');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $this->assertSame(12.3456, $method->invoke($rowParser, '12.3456'));
    }
}
