<?php

/**
 * DHParameter
 *
 * From: https://www.teletrust.de/fileadmin/files/oid/oid_pkcs-3v1-4.pdf#page=6
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
 * DHParameter
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class DHParameter
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['prime' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER], 'base' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER], 'privateValueLength' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_INTEGER, 'optional' => \true]]];
}
