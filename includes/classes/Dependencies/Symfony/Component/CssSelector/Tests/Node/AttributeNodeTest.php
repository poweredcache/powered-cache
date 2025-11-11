<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PoweredCache\Dependencies\Symfony\Component\CssSelector\Tests\Node;

use PoweredCache\Dependencies\Symfony\Component\CssSelector\Node\AttributeNode;
use PoweredCache\Dependencies\Symfony\Component\CssSelector\Node\ElementNode;

class AttributeNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new AttributeNode(new ElementNode(), null, 'attribute', 'exists', null), 'Powered_Cache_Attribute[Element[*][attribute]]'],
            [new AttributeNode(new ElementNode(), null, 'attribute', '$=', 'value'), "Powered_Cache_Attribute[Element[*][attribute $= 'value']]"],
            [new AttributeNode(new ElementNode(), 'namespace', 'attribute', '$=', 'value'), "Powered_Cache_Attribute[Element[*][namespace|attribute $= 'value']]"],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new AttributeNode(new ElementNode(), null, 'attribute', 'exists', null), 10],
            [new AttributeNode(new ElementNode(null, 'element'), null, 'attribute', 'exists', null), 11],
            [new AttributeNode(new ElementNode(), null, 'attribute', '$=', 'value'), 10],
            [new AttributeNode(new ElementNode(), 'namespace', 'attribute', '$=', 'value'), 10],
        ];
    }
}
