<?php

/**
 * prime192v2
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
class prime192v2 extends \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Prime
{
    public function __construct()
    {
        $this->setModulo(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFFFFFFFFFFFF', 16));
        $this->setCoefficients(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFFFFFFFFFFFC', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('CC22D6DFB95C6B25E49C0D6364A4E5980C393AA21668D953', 16));
        $this->setBasePoint(new \FluentSmtpLib\phpseclib3\Math\BigInteger('EEA2BAE7E1497842F2DE7769CFE9C989C072AD696F48034A', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('6574D11D69B6EC7A672BB82A083DF2F2B0847DE970B2DE15', 16));
        $this->setOrder(new \FluentSmtpLib\phpseclib3\Math\BigInteger('FFFFFFFFFFFFFFFFFFFFFFFE5FB1A724DC80418648D8DD31', 16));
    }
}
