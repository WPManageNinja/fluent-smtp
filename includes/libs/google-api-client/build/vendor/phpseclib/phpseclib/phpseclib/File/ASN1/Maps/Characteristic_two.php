<?php

/**
 * Characteristic_two
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
 * Characteristic_two
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class Characteristic_two
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => [
        'm' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER],
        // field size 2**m
        'basis' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_OBJECT_IDENTIFIER],
        'parameters' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_ANY, 'optional' => \true],
    ]];
}
