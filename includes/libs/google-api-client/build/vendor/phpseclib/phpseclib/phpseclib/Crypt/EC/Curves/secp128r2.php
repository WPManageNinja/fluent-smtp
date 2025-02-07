<?php

/**
 * secp128r2
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
class secp128r2 extends \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Prime
{
    public function __construct()
    {
        // same as secp128r1
        $this->setModulo(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFDFFFFFFFFFFFFFFFFFFFFFFFF', 16));
        $this->setCoefficients(new \FluentSmtpLib\phpseclib3\Math\BigInteger('D6031998D1B3BBFEBF59CC9BBFF9AEE1', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('5EEEFCA380D02919DC2C6558BB6D8A5D', 16));
        $this->setBasePoint(new \FluentSmtpLib\phpseclib3\Math\BigInteger('7B6AA5D85E572983E6FB32A7CDEBC140', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('27B6916A894D3AEE7106FE805FC34B44', 16));
        $this->setOrder(new \FluentSmtpLib\phpseclib3\Math\BigInteger('3FFFFFFF7FFFFFFFBE0024720613B5A3', 16));
    }
}
