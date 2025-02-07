<?php

/**
 * RC2CBCParameter
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
 * RC2CBCParameter
 *
 * from https://tools.ietf.org/html/rfc2898#appendix-A.3
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class RC2CBCParameter
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['rc2ParametersVersion' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER, 'optional' => \true], 'iv' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_OCTET_STRING]]];
}
