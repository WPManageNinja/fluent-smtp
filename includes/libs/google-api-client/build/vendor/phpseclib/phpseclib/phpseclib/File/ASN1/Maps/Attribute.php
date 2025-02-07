<?php

/**
 * Attribute
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\File\ASN1\Maps;

use FluentSmtpLib\phpseclib3\File\ASN1;
/**
 * Attribute
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class Attribute
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\AttributeType::MAP, 'value' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SET, 'min' => 1, 'max' => -1, 'children' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\AttributeValue::MAP]]];
}
