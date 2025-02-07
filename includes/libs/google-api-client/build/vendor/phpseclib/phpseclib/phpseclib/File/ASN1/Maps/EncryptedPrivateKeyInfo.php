<?php

/**
 * EncryptedPrivateKeyInfo
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
 * EncryptedPrivateKeyInfo
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class EncryptedPrivateKeyInfo
{
    const MAP = ['type' => \FluentSmtpLib\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['encryptionAlgorithm' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\AlgorithmIdentifier::MAP, 'encryptedData' => \FluentSmtpLib\phpseclib3\File\ASN1\Maps\EncryptedData::MAP]];
}
