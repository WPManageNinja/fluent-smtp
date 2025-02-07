<?php

/**
 * RevokedCertificate
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
 * RevokedCertificate
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class RevokedCertificate
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['userCertificate' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\CertificateSerialNumber::MAP, 'revocationDate' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\Time::MAP, 'crlEntryExtensions' => ['optional' => \true] + \FluentSmtpLib\phpseclib3\File\ASN1\Maps\Extensions::MAP]];
}
