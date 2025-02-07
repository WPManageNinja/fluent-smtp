<?php

/**
 * AnotherName
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
 * AnotherName
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class AnotherName
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['type-id' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_OBJECT_IDENTIFIER], 'value' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_ANY, 'constant' => 0, 'optional' => \true, 'explicit' => \true]]];
}
