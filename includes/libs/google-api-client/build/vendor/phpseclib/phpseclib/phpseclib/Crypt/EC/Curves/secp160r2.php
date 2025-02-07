<?php

/**
 * secp160r2
 *
 * PHP version 5 and 7
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2017 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://pear.php.net/package/Math_BigInteger
 */
namespace FluentSmtpLib\phpseclib3\Crypt\EC\Curves;

use FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Prime;
use FluentSmtpLib\phpseclib3\Math\BigInteger;
class secp160r2 extends \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Prime
{
    public function __construct()
    {
        // same as secp160k1
        $this->setModulo(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFAC73', 16));
        $this->setCoefficients(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFAC70', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('B4E134D3FB59EB8BAB57274904664D5AF50388BA', 16));
        $this->setBasePoint(new \FluentSmtpLib\phpseclib3\Math\BigInteger('52DCB034293A117E1F4FF11B30F7199D3144CE6D', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('FEAFFEF2E331F296E071FA0DF9982CFEA7D43F2E', 16));
        $this->setOrder(new \FluentSmtpLib\phpseclib3\Math\BigInteger('0100000000000000000000351EE786A818F3A1A16B', 16));
    }
}
