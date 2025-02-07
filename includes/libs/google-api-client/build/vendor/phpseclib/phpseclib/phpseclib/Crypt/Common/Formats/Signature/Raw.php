<?php

/**
 * Raw Signature Handler
 *
 * PHP version 5
 *
 * Handles signatures as arrays
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib3\Crypt\Common\Formats\Signature;

use FluentSmtpLib\phpseclib3\Math\BigInteger;
/**
 * Raw Signature Handler
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class Raw
{
    /**
     * Loads a signature
     *
     * @param array $sig
     * @return array|bool
     */
    public static function load($sig)
    {
        switch (\true) {
            case !\is_array($sig):
            case !isset($sig['r']) || !isset($sig['s']):
            case !$sig['r'] instanceof \FluentSmtpLib\phpseclib3\Math\BigInteger:
            case !$sig['s'] instanceof \FluentSmtpLib\phpseclib3\Math\BigInteger:
                return \false;
        }
        return ['r' => $sig['r'], 's' => $sig['s']];
    }
    /**
     * Returns a signature in the appropriate format
     *
     * @param BigInteger $r
     * @param BigInteger $s
     * @return string
     */
    public static function save(\FluentSmtpLib\phpseclib3\Math\BigInteger $r, \FluentSmtpLib\phpseclib3\Math\BigInteger $s)
    {
        return \compact('r', 's');
    }
}
