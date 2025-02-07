<?php

/**
 * Extension
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
 * Extension
 *
 * A certificate using system MUST reject the certificate if it encounters
 * a critical extension it does not recognize; however, a non-critical
 * extension may be ignored if it is not recognized.
 *
 * http://tools.ietf.org/html/rfc5280#section-4.2
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class Extension
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['extnId' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_OBJECT_IDENTIFIER], 'critical' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_BOOLEAN, 'optional' => \true, 'default' => \false], 'extnValue' => ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_OCTET_STRING]]];
}
