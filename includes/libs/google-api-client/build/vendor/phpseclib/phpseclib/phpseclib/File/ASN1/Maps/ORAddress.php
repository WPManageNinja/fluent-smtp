<?php

/**
 * ORAddress
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
 * ORAddress
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class ORAddress
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['built-in-standard-attributes' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\BuiltInStandardAttributes::MAP, 'built-in-domain-defined-attributes' => ['optional' => \true] + \FluentSmtpLib\phpseclib3\File\ASN1\Maps\BuiltInDomainDefinedAttributes::MAP, 'extension-attributes' => ['optional' => \true] + \FluentSmtpLib\phpseclib3\File\ASN1\Maps\ExtensionAttributes::MAP]];
}
