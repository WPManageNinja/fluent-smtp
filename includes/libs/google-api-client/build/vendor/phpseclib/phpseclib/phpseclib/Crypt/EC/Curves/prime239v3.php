<?php

/**
 * prime239v3
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
class prime239v3 extends \FluentSmtpLib\phpseclib3\Crypt\EC\BaseCurves\Prime
{
    public function __construct()
    {
        $this->setModulo(new \FluentSmtpLib\phpseclib3\Math\BigInteger('7FFFFFFFFFFFFFFFFFFFFFFF7FFFFFFFFFFF8000000000007FFFFFFFFFFF', 16));
        $this->setCoefficients(new \FluentSmtpLib\phpseclib3\Math\BigInteger('7FFFFFFFFFFFFFFFFFFFFFFF7FFFFFFFFFFF8000000000007FFFFFFFFFFC', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('255705FA2A306654B1F4CB03D6A750A30C250102D4988717D9BA15AB6D3E', 16));
        $this->setBasePoint(new \FluentSmtpLib\phpseclib3\Math\BigInteger('6768AE8E18BB92CFCF005C949AA2C6D94853D0E660BBF854B1C9505FE95A', 16), new \FluentSmtpLib\phpseclib3\Math\BigInteger('1607E6898F390C06BC1D552BAD226F3B6FCFE48B6E818499AF18E3ED6CF3', 16));
        $this->setOrder(new \FluentSmtpLib\phpseclib3\Math\BigInteger('7FFFFFFFFFFFFFFFFFFFFFFF7FFFFF975DEB41B3A6057C3C432146526551', 16));
    }
}
