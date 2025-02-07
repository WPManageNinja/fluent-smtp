<?php

/**
 * Montgomery Public Key Handler
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2015 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt\EC\Formats\Keys;

use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery as MontgomeryCurve;
use FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Curve25519;
use FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Curve448;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
/**
 * Montgomery Public Key Handler
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class MontgomeryPublic
{
    /**
     * Is invisible flag
     *
     */
    const IS_INVISIBLE = \true;
    /**
     * Break a public or private key down into its constituent components
     *
     * @param string $key
     * @param string $password optional
     * @return array
     */
    public static function load($key, $password = '')
    {
        switch (\strlen($key)) {
            case 32:
                $curve = new \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Curve25519();
                break;
            case 56:
                $curve = new \FluentSmtpLib\phpseclib3\Crypt\EC\Curves\Curve448();
                break;
            default:
                throw new \LengthException('The only supported lengths are 32 and 56');
        }
        $components = ['curve' => $curve];
        $components['QA'] = [$components['curve']->convertInteger(new \FluentSmtpLib\phpseclib3\Math\BigInteger(\strrev($key), 256))];
        return $components;
    }
    /**
     * Convert an EC public key to the appropriate format
     *
     * @param MontgomeryCurve $curve
     * @param \phpseclib3\Math\Common\FiniteField\Integer[] $publicKey
     * @return string
     */
    public static function savePublicKey(\FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Montgomery $curve, array $publicKey)
    {
        return \strrev($publicKey[0]->toBytes());
    }
}
