<?php

/**
 * Pure-PHP implementation of Rijndael.
 *
 * Uses mcrypt, if available/possible, and an internal implementation, otherwise.
 *
 * PHP version 5
 *
 * If {@link self::setBlockLength() setBlockLength()} isn't called, it'll be assumed to be 128 bits.  If
 * {@link self::setKeyLength() setKeyLength()} isn't called, it'll be calculated from
 * {@link self::setKey() setKey()}.  ie. if the key is 128-bits, the key length will be 128-bits.  If it's
 * 136-bits it'll be null-padded to 192-bits and 192 bits will be the key length until
 * {@link self::setKey() setKey()} is called, again, at which point, it'll be recalculated.
 *
 * Not all Rijndael implementations may support 160-bits or 224-bits as the block length / key length.  mcrypt, for example,
 * does not.  AES, itself, only supports block lengths of 128 and key lengths of 128, 192, and 256.
 * {@link http://csrc.nist.gov/archive/aes/rijndael/Rijndael-ammended.pdf#page=10 Rijndael-ammended.pdf#page=10} defines the
 * algorithm for block lengths of 192 and 256 but not for block lengths / key lengths of 160 and 224.  Indeed, 160 and 224
 * are first defined as valid key / block lengths in
 * {@link http://csrc.nist.gov/archive/aes/rijndael/Rijndael-ammended.pdf#page=44 Rijndael-ammended.pdf#page=44}:
 * Extensions: Other block and Cipher Key lengths.
 * Note: Use of 160/224-bit Keys must be explicitly set by setKeyLength(160) respectively setKeyLength(224).
 *
 * {@internal The variable names are the same as those in
 * {@link http://www.csrc.nist.gov/publications/fips/fips197/fips-197.pdf#page=10 fips-197.pdf#page=10}.}}
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include 'vendor/autoload.php';
 *
 *    $rijndael = new \phpseclib\Crypt\Rijndael();
 *
 *    $rijndael->setKey('abcdefghijklmnop');
 *
 *    $size = 10 * 1024;
 *    $plaintext = '';
 *    for ($i = 0; $i < $size; $i++) {
 *        $plaintext.= 'a';
 *    }
 *
 *    echo $rijndael->decrypt($rijndael->encrypt($plaintext));
 * ?>
 * </code>
 *
 * @category  Crypt
 * @package   Rijndael
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2008 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace FluentSmtpLib\phpseclib\Crypt;

/**
 * Pure-PHP implementation of Rijndael.
 *
 * @package Rijndael
 * @author  Jim Wigginton <terrafrost@php.net>
 * @access  public
 */
class Rijndael extends Base
{
    /**
     * The mcrypt specific name of the cipher
     *
     * Mcrypt is useable for 128/192/256-bit $block_size/$key_length. For 160/224 not.
     * \phpseclib\Crypt\Rijndael determines automatically whether mcrypt is useable
     * or not for the current $block_size/$key_length.
     * In case of, $cipher_name_mcrypt will be set dynamically at run time accordingly.
     *
     * @see \phpseclib\Crypt\Base::cipher_name_mcrypt
     * @see \phpseclib\Crypt\Base::engine
     * @see self::isValidEngine()
     * @var string
     * @access private
     */
    var $cipher_name_mcrypt = 'rijndael-128';
    /**
     * The default salt used by setPassword()
     *
     * @see \phpseclib\Crypt\Base::password_default_salt
     * @see \phpseclib\Crypt\Base::setPassword()
     * @var string
     * @access private
     */
    var $password_default_salt = 'phpseclib';
    /**
     * The Key Schedule
     *
     * @see self::_setup()
     * @var array
     * @access private
     */
    var $w;
    /**
     * The Inverse Key Schedule
     *
     * @see self::_setup()
     * @var array
     * @access private
     */
    var $dw;
    /**
     * The Block Length divided by 32
     *
     * @see self::setBlockLength()
     * @var int
     * @access private
     * @internal The max value is 256 / 32 = 8, the min value is 128 / 32 = 4.  Exists in conjunction with $block_size
     *    because the encryption / decryption / key schedule creation requires this number and not $block_size.  We could
     *    derive this from $block_size or vice versa, but that'd mean we'd have to do multiple shift operations, so in lieu
     *    of that, we'll just precompute it once.
     */
    var $Nb = 4;
    /**
     * The Key Length (in bytes)
     *
     * @see self::setKeyLength()
     * @var int
     * @access private
     * @internal The max value is 256 / 8 = 32, the min value is 128 / 8 = 16.  Exists in conjunction with $Nk
     *    because the encryption / decryption / key schedule creation requires this number and not $key_length.  We could
     *    derive this from $key_length or vice versa, but that'd mean we'd have to do multiple shift operations, so in lieu
     *    of that, we'll just precompute it once.
     */
    var $key_length = 16;
    /**
     * The Key Length divided by 32
     *
     * @see self::setKeyLength()
     * @var int
     * @access private
     * @internal The max value is 256 / 32 = 8, the min value is 128 / 32 = 4
     */
    var $Nk = 4;
    /**
     * The Number of Rounds
     *
     * @var int
     * @access private
     * @internal The max value is 14, the min value is 10.
     */
    var $Nr;
    /**
     * Shift offsets
     *
     * @var array
     * @access private
     */
    var $c;
    /**
     * Holds the last used key- and block_size information
     *
     * @var array
     * @access private
     */
    var $kl;
    /**
     * Sets the key length.
     *
     * Valid key lengths are 128, 160, 192, 224, and 256.  If the length is less than 128, it will be rounded up to
     * 128.  If the length is greater than 128 and invalid, it will be rounded down to the closest valid amount.
     *
     * Note: phpseclib extends Rijndael (and AES) for using 160- and 224-bit keys but they are officially not defined
     *       and the most (if not all) implementations are not able using 160/224-bit keys but round/pad them up to
     *       192/256 bits as, for example, mcrypt will do.
     *
     *       That said, if you want be compatible with other Rijndael and AES implementations,
     *       you should not setKeyLength(160) or setKeyLength(224).
     *
     * Additional: In case of 160- and 224-bit keys, phpseclib will/can, for that reason, not use
     *             the mcrypt php extension, even if available.
     *             This results then in slower encryption.
     *
     * @access public
     * @param int $length
     */
    function setKeyLength($length)
    {
        switch (\true) {
            case $length <= 128:
                $this->key_length = 16;
                break;
            case $length <= 160:
                $this->key_length = 20;
                break;
            case $length <= 192:
                $this->key_length = 24;
                break;
            case $length <= 224:
                $this->key_length = 28;
                break;
            default:
                $this->key_length = 32;
        }
        parent::setKeyLength($length);
    }
    /**
     * Sets the block length
     *
     * Valid block lengths are 128, 160, 192, 224, and 256.  If the length is less than 128, it will be rounded up to
     * 128.  If the length is greater than 128 and invalid, it will be rounded down to the closest valid amount.
     *
     * @access public
     * @param int $length
     */
    function setBlockLength($length)
    {
        $length >>= 5;
        if ($length > 8) {
            $length = 8;
        } elseif ($length < 4) {
            $length = 4;
        }
        $this->Nb = $length;
        $this->block_size = $length << 2;
        $this->changed = \true;
        $this->_setEngine();
    }
    /**
     * Test for engine validity
     *
     * This is mainly just a wrapper to set things up for \phpseclib\Crypt\Base::isValidEngine()
     *
     * @see \phpseclib\Crypt\Base::__construct()
     * @param int $engine
     * @access public
     * @return bool
     */
    function isValidEngine($engine)
    {
        switch ($engine) {
            case self::ENGINE_OPENSSL:
                if ($this->block_size != 16) {
                    return \false;
                }
                $this->cipher_name_openssl_ecb = 'aes-' . ($this->key_length << 3) . '-ecb';
                $this->cipher_name_openssl = 'aes-' . ($this->key_length << 3) . '-' . $this->_openssl_translate_mode();
                break;
            case self::ENGINE_MCRYPT:
                $this->cipher_name_mcrypt = 'rijndael-' . ($this->block_size << 3);
                if ($this->key_length % 8) {
                    // is it a 160/224-bit key?
                    // mcrypt is not usable for them, only for 128/192/256-bit keys
                    return \false;
                }
        }
        return parent::isValidEngine($engine);
    }
    /**
     * Encrypts a block
     *
     * @access private
     * @param string $in
     * @return string
     */
    function _encryptBlock($in)
    {
        static $tables;
        if (empty($tables)) {
            $tables =& $this->_getTables();
        }
        $t0 = $tables[0];
        $t1 = $tables[1];
        $t2 = $tables[2];
        $t3 = $tables[3];
        $sbox = $tables[4];
        $state = array();
        $words = \unpack('N*', $in);
        $c = $this->c;
        $w = $this->w;
        $Nb = $this->Nb;
        $Nr = $this->Nr;
        // addRoundKey
        $wc = $Nb - 1;
        foreach ($words as $word) {
            $state[] = $word ^ $w[++$wc];
        }
        // fips-197.pdf#page=19, "Figure 5. Pseudo Code for the Cipher", states that this loop has four components -
        // subBytes, shiftRows, mixColumns, and addRoundKey. fips-197.pdf#page=30, "Implementation Suggestions Regarding
        // Various Platforms" suggests that performs enhanced implementations are described in Rijndael-ammended.pdf.
        // Rijndael-ammended.pdf#page=20, "Implementation aspects / 32-bit processor", discusses such an optimization.
        // Unfortunately, the description given there is not quite correct.  Per aes.spec.v316.pdf#page=19 [1],
        // equation (7.4.7) is supposed to use addition instead of subtraction, so we'll do that here, as well.
        // [1] http://fp.gladman.plus.com/cryptography_technology/rijndael/aes.spec.v316.pdf
        $temp = array();
        for ($round = 1; $round < $Nr; ++$round) {
            $i = 0;
            // $c[0] == 0
            $j = $c[1];
            $k = $c[2];
            $l = $c[3];
            while ($i < $Nb) {
                $temp[$i] = $t0[$state[$i] >> 24 & 0xff] ^ $t1[$state[$j] >> 16 & 0xff] ^ $t2[$state[$k] >> 8 & 0xff] ^ $t3[$state[$l] & 0xff] ^ $w[++$wc];
                ++$i;
                $j = ($j + 1) % $Nb;
                $k = ($k + 1) % $Nb;
                $l = ($l + 1) % $Nb;
            }
            $state = $temp;
        }
        // subWord
        for ($i = 0; $i < $Nb; ++$i) {
            $state[$i] = $sbox[$state[$i] & 0xff] | $sbox[$state[$i] >> 8 & 0xff] << 8 | $sbox[$state[$i] >> 16 & 0xff] << 16 | $sbox[$state[$i] >> 24 & 0xff] << 24;
        }
        // shiftRows + addRoundKey
        $i = 0;
        // $c[0] == 0
        $j = $c[1];
        $k = $c[2];
        $l = $c[3];
        while ($i < $Nb) {
            $temp[$i] = $state[$i] & \intval(0xff000000) ^ $state[$j] & 0xff0000 ^ $state[$k] & 0xff00 ^ $state[$l] & 0xff ^ $w[$i];
            ++$i;
            $j = ($j + 1) % $Nb;
            $k = ($k + 1) % $Nb;
            $l = ($l + 1) % $Nb;
        }
        switch ($Nb) {
            case 8:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5], $temp[6], $temp[7]);
            case 7:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5], $temp[6]);
            case 6:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5]);
            case 5:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4]);
            default:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3]);
        }
    }
    /**
     * Decrypts a block
     *
     * @access private
     * @param string $in
     * @return string
     */
    function _decryptBlock($in)
    {
        static $invtables;
        if (empty($invtables)) {
            $invtables =& $this->_getInvTables();
        }
        $dt0 = $invtables[0];
        $dt1 = $invtables[1];
        $dt2 = $invtables[2];
        $dt3 = $invtables[3];
        $isbox = $invtables[4];
        $state = array();
        $words = \unpack('N*', $in);
        $c = $this->c;
        $dw = $this->dw;
        $Nb = $this->Nb;
        $Nr = $this->Nr;
        // addRoundKey
        $wc = $Nb - 1;
        foreach ($words as $word) {
            $state[] = $word ^ $dw[++$wc];
        }
        $temp = array();
        for ($round = $Nr - 1; $round > 0; --$round) {
            $i = 0;
            // $c[0] == 0
            $j = $Nb - $c[1];
            $k = $Nb - $c[2];
            $l = $Nb - $c[3];
            while ($i < $Nb) {
                $temp[$i] = $dt0[$state[$i] >> 24 & 0xff] ^ $dt1[$state[$j] >> 16 & 0xff] ^ $dt2[$state[$k] >> 8 & 0xff] ^ $dt3[$state[$l] & 0xff] ^ $dw[++$wc];
                ++$i;
                $j = ($j + 1) % $Nb;
                $k = ($k + 1) % $Nb;
                $l = ($l + 1) % $Nb;
            }
            $state = $temp;
        }
        // invShiftRows + invSubWord + addRoundKey
        $i = 0;
        // $c[0] == 0
        $j = $Nb - $c[1];
        $k = $Nb - $c[2];
        $l = $Nb - $c[3];
        while ($i < $Nb) {
            $word = $state[$i] & \intval(0xff000000) | $state[$j] & 0xff0000 | $state[$k] & 0xff00 | $state[$l] & 0xff;
            $temp[$i] = $dw[$i] ^ ($isbox[$word & 0xff] | $isbox[$word >> 8 & 0xff] << 8 | $isbox[$word >> 16 & 0xff] << 16 | $isbox[$word >> 24 & 0xff] << 24);
            ++$i;
            $j = ($j + 1) % $Nb;
            $k = ($k + 1) % $Nb;
            $l = ($l + 1) % $Nb;
        }
        switch ($Nb) {
            case 8:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5], $temp[6], $temp[7]);
            case 7:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5], $temp[6]);
            case 6:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4], $temp[5]);
            case 5:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3], $temp[4]);
            default:
                return \pack('N*', $temp[0], $temp[1], $temp[2], $temp[3]);
        }
    }
    /**
     * Setup the key (expansion)
     *
     * @see \phpseclib\Crypt\Base::_setupKey()
     * @access private
     */
    function _setupKey()
    {
        // Each number in $rcon is equal to the previous number multiplied by two in Rijndael's finite field.
        // See http://en.wikipedia.org/wiki/Finite_field_arithmetic#Multiplicative_inverse
        static $rcon;
        if (!isset($rcon)) {
            $rcon = array(0, 0x1000000, 0x2000000, 0x4000000, 0x8000000, 0x10000000, 0x20000000, 0x40000000, 0x80000000, 0x1b000000, 0x36000000, 0x6c000000, 0xd8000000, 0xab000000, 0x4d000000, 0x9a000000, 0x2f000000, 0x5e000000, 0xbc000000, 0x63000000, 0xc6000000, 0x97000000, 0x35000000, 0x6a000000, 0xd4000000, 0xb3000000, 0x7d000000, 0xfa000000, 0xef000000, 0xc5000000, 0x91000000);
            $rcon = \array_map('intval', $rcon);
        }
        if (isset($this->kl['key']) && $this->key === $this->kl['key'] && $this->key_length === $this->kl['key_length'] && $this->block_size === $this->kl['block_size']) {
            // already expanded
            return;
        }
        $this->kl = array('key' => $this->key, 'key_length' => $this->key_length, 'block_size' => $this->block_size);
        $this->Nk = $this->key_length >> 2;
        // see Rijndael-ammended.pdf#page=44
        $this->Nr = \max($this->Nk, $this->Nb) + 6;
        // shift offsets for Nb = 5, 7 are defined in Rijndael-ammended.pdf#page=44,
        //     "Table 8: Shift offsets in Shiftrow for the alternative block lengths"
        // shift offsets for Nb = 4, 6, 8 are defined in Rijndael-ammended.pdf#page=14,
        //     "Table 2: Shift offsets for different block lengths"
        switch ($this->Nb) {
            case 4:
            case 5:
            case 6:
                $this->c = array(0, 1, 2, 3);
                break;
            case 7:
                $this->c = array(0, 1, 2, 4);
                break;
            case 8:
                $this->c = array(0, 1, 3, 4);
        }
        $w = \array_values(\unpack('N*words', $this->key));
        $length = $this->Nb * ($this->Nr + 1);
        for ($i = $this->Nk; $i < $length; $i++) {
            $temp = $w[$i - 1];
            if ($i % $this->Nk == 0) {
                // according to <http://php.net/language.types.integer>, "the size of an integer is platform-dependent".
                // on a 32-bit machine, it's 32-bits, and on a 64-bit machine, it's 64-bits. on a 32-bit machine,
                // 0xFFFFFFFF << 8 == 0xFFFFFF00, but on a 64-bit machine, it equals 0xFFFFFFFF00. as such, doing 'and'
                // with 0xFFFFFFFF (or 0xFFFFFF00) on a 32-bit machine is unnecessary, but on a 64-bit machine, it is.
                $temp = $temp << 8 & \intval(0xffffff00) | $temp >> 24 & 0xff;
                // rotWord
                $temp = $this->_subWord($temp) ^ $rcon[$i / $this->Nk];
            } elseif ($this->Nk > 6 && $i % $this->Nk == 4) {
                $temp = $this->_subWord($temp);
            }
            $w[$i] = $w[$i - $this->Nk] ^ $temp;
        }
        // convert the key schedule from a vector of $Nb * ($Nr + 1) length to a matrix with $Nr + 1 rows and $Nb columns
        // and generate the inverse key schedule.  more specifically,
        // according to <http://csrc.nist.gov/archive/aes/rijndael/Rijndael-ammended.pdf#page=23> (section 5.3.3),
        // "The key expansion for the Inverse Cipher is defined as follows:
        //        1. Apply the Key Expansion.
        //        2. Apply InvMixColumn to all Round Keys except the first and the last one."
        // also, see fips-197.pdf#page=27, "5.3.5 Equivalent Inverse Cipher"
        list($dt0, $dt1, $dt2, $dt3) = $this->_getInvTables();
        $temp = $this->w = $this->dw = array();
        for ($i = $row = $col = 0; $i < $length; $i++, $col++) {
            if ($col == $this->Nb) {
                if ($row == 0) {
                    $this->dw[0] = $this->w[0];
                } else {
                    // subWord + invMixColumn + invSubWord = invMixColumn
                    $j = 0;
                    while ($j < $this->Nb) {
                        $dw = $this->_subWord($this->w[$row][$j]);
                        $temp[$j] = $dt0[$dw >> 24 & 0xff] ^ $dt1[$dw >> 16 & 0xff] ^ $dt2[$dw >> 8 & 0xff] ^ $dt3[$dw & 0xff];
                        $j++;
                    }
                    $this->dw[$row] = $temp;
                }
                $col = 0;
                $row++;
            }
            $this->w[$row][$col] = $w[$i];
        }
        $this->dw[$row] = $this->w[$row];
        // Converting to 1-dim key arrays (both ascending)
        $this->dw = \array_reverse($this->dw);
        $w = \array_pop($this->w);
        $dw = \array_pop($this->dw);
        foreach ($this->w as $r => $wr) {
            foreach ($wr as $c => $wc) {
                $w[] = $wc;
                $dw[] = $this->dw[$r][$c];
            }
        }
        $this->w = $w;
        $this->dw = $dw;
    }
    /**
     * Performs S-Box substitutions
     *
     * @access private
     * @param int $word
     */
    function _subWord($word)
    {
        static $sbox;
        if (empty($sbox)) {
            list(, , , , $sbox) = $this->_getTables();
        }
        return $sbox[$word & 0xff] | $sbox[$word >> 8 & 0xff] << 8 | $sbox[$word >> 16 & 0xff] << 16 | $sbox[$word >> 24 & 0xff] << 24;
    }
    /**
     * Provides the mixColumns and sboxes tables
     *
     * @see self::_encryptBlock()
     * @see self::_setupInlineCrypt()
     * @see self::_subWord()
     * @access private
     * @return array &$tables
     */
    function &_getTables()
    {
        static $tables;
        if (empty($tables)) {
            // according to <http://csrc.nist.gov/archive/aes/rijndael/Rijndael-ammended.pdf#page=19> (section 5.2.1),
            // precomputed tables can be used in the mixColumns phase. in that example, they're assigned t0...t3, so
            // those are the names we'll use.
            $t3 = \array_map('intval', array(
                // with array_map('intval', ...) we ensure we have only int's and not
                // some slower floats converted by php automatically on high values
                0x6363a5c6,
                0x7c7c84f8,
                0x777799ee,
                0x7b7b8df6,
                0xf2f20dff,
                0x6b6bbdd6,
                0x6f6fb1de,
                0xc5c55491,
                0x30305060,
                0x1010302,
                0x6767a9ce,
                0x2b2b7d56,
                0xfefe19e7,
                0xd7d762b5,
                0xababe64d,
                0x76769aec,
                0xcaca458f,
                0x82829d1f,
                0xc9c94089,
                0x7d7d87fa,
                0xfafa15ef,
                0x5959ebb2,
                0x4747c98e,
                0xf0f00bfb,
                0xadadec41,
                0xd4d467b3,
                0xa2a2fd5f,
                0xafafea45,
                0x9c9cbf23,
                0xa4a4f753,
                0x727296e4,
                0xc0c05b9b,
                0xb7b7c275,
                0xfdfd1ce1,
                0x9393ae3d,
                0x26266a4c,
                0x36365a6c,
                0x3f3f417e,
                0xf7f702f5,
                0xcccc4f83,
                0x34345c68,
                0xa5a5f451,
                0xe5e534d1,
                0xf1f108f9,
                0x717193e2,
                0xd8d873ab,
                0x31315362,
                0x15153f2a,
                0x4040c08,
                0xc7c75295,
                0x23236546,
                0xc3c35e9d,
                0x18182830,
                0x9696a137,
                0x5050f0a,
                0x9a9ab52f,
                0x707090e,
                0x12123624,
                0x80809b1b,
                0xe2e23ddf,
                0xebeb26cd,
                0x2727694e,
                0xb2b2cd7f,
                0x75759fea,
                0x9091b12,
                0x83839e1d,
                0x2c2c7458,
                0x1a1a2e34,
                0x1b1b2d36,
                0x6e6eb2dc,
                0x5a5aeeb4,
                0xa0a0fb5b,
                0x5252f6a4,
                0x3b3b4d76,
                0xd6d661b7,
                0xb3b3ce7d,
                0x29297b52,
                0xe3e33edd,
                0x2f2f715e,
                0x84849713,
                0x5353f5a6,
                0xd1d168b9,
                0x0,
                0xeded2cc1,
                0x20206040,
                0xfcfc1fe3,
                0xb1b1c879,
                0x5b5bedb6,
                0x6a6abed4,
                0xcbcb468d,
                0xbebed967,
                0x39394b72,
                0x4a4ade94,
                0x4c4cd498,
                0x5858e8b0,
                0xcfcf4a85,
                0xd0d06bbb,
                0xefef2ac5,
                0xaaaae54f,
                0xfbfb16ed,
                0x4343c586,
                0x4d4dd79a,
                0x33335566,
                0x85859411,
                0x4545cf8a,
                0xf9f910e9,
                0x2020604,
                0x7f7f81fe,
                0x5050f0a0,
                0x3c3c4478,
                0x9f9fba25,
                0xa8a8e34b,
                0x5151f3a2,
                0xa3a3fe5d,
                0x4040c080,
                0x8f8f8a05,
                0x9292ad3f,
                0x9d9dbc21,
                0x38384870,
                0xf5f504f1,
                0xbcbcdf63,
                0xb6b6c177,
                0xdada75af,
                0x21216342,
                0x10103020,
                0xffff1ae5,
                0xf3f30efd,
                0xd2d26dbf,
                0xcdcd4c81,
                0xc0c1418,
                0x13133526,
                0xecec2fc3,
                0x5f5fe1be,
                0x9797a235,
                0x4444cc88,
                0x1717392e,
                0xc4c45793,
                0xa7a7f255,
                0x7e7e82fc,
                0x3d3d477a,
                0x6464acc8,
                0x5d5de7ba,
                0x19192b32,
                0x737395e6,
                0x6060a0c0,
                0x81819819,
                0x4f4fd19e,
                0xdcdc7fa3,
                0x22226644,
                0x2a2a7e54,
                0x9090ab3b,
                0x8888830b,
                0x4646ca8c,
                0xeeee29c7,
                0xb8b8d36b,
                0x14143c28,
                0xdede79a7,
                0x5e5ee2bc,
                0xb0b1d16,
                0xdbdb76ad,
                0xe0e03bdb,
                0x32325664,
                0x3a3a4e74,
                0xa0a1e14,
                0x4949db92,
                0x6060a0c,
                0x24246c48,
                0x5c5ce4b8,
                0xc2c25d9f,
                0xd3d36ebd,
                0xacacef43,
                0x6262a6c4,
                0x9191a839,
                0x9595a431,
                0xe4e437d3,
                0x79798bf2,
                0xe7e732d5,
                0xc8c8438b,
                0x3737596e,
                0x6d6db7da,
                0x8d8d8c01,
                0xd5d564b1,
                0x4e4ed29c,
                0xa9a9e049,
                0x6c6cb4d8,
                0x5656faac,
                0xf4f407f3,
                0xeaea25cf,
                0x6565afca,
                0x7a7a8ef4,
                0xaeaee947,
                0x8081810,
                0xbabad56f,
                0x787888f0,
                0x25256f4a,
                0x2e2e725c,
                0x1c1c2438,
                0xa6a6f157,
                0xb4b4c773,
                0xc6c65197,
                0xe8e823cb,
                0xdddd7ca1,
                0x74749ce8,
                0x1f1f213e,
                0x4b4bdd96,
                0xbdbddc61,
                0x8b8b860d,
                0x8a8a850f,
                0x707090e0,
                0x3e3e427c,
                0xb5b5c471,
                0x6666aacc,
                0x4848d890,
                0x3030506,
                0xf6f601f7,
                0xe0e121c,
                0x6161a3c2,
                0x35355f6a,
                0x5757f9ae,
                0xb9b9d069,
                0x86869117,
                0xc1c15899,
                0x1d1d273a,
                0x9e9eb927,
                0xe1e138d9,
                0xf8f813eb,
                0x9898b32b,
                0x11113322,
                0x6969bbd2,
                0xd9d970a9,
                0x8e8e8907,
                0x9494a733,
                0x9b9bb62d,
                0x1e1e223c,
                0x87879215,
                0xe9e920c9,
                0xcece4987,
                0x5555ffaa,
                0x28287850,
                0xdfdf7aa5,
                0x8c8c8f03,
                0xa1a1f859,
                0x89898009,
                0xd0d171a,
                0xbfbfda65,
                0xe6e631d7,
                0x4242c684,
                0x6868b8d0,
                0x4141c382,
                0x9999b029,
                0x2d2d775a,
                0xf0f111e,
                0xb0b0cb7b,
                0x5454fca8,
                0xbbbbd66d,
                0x16163a2c,
            ));
            foreach ($t3 as $t3i) {
                $t0[] = $t3i << 24 & \intval(0xff000000) | $t3i >> 8 & 0xffffff;
                $t1[] = $t3i << 16 & \intval(0xffff0000) | $t3i >> 16 & 0xffff;
                $t2[] = $t3i << 8 & \intval(0xffffff00) | $t3i >> 24 & 0xff;
            }
            $tables = array(
                // The Precomputed mixColumns tables t0 - t3
                $t0,
                $t1,
                $t2,
                $t3,
                // The SubByte S-Box
                array(0x63, 0x7c, 0x77, 0x7b, 0xf2, 0x6b, 0x6f, 0xc5, 0x30, 0x1, 0x67, 0x2b, 0xfe, 0xd7, 0xab, 0x76, 0xca, 0x82, 0xc9, 0x7d, 0xfa, 0x59, 0x47, 0xf0, 0xad, 0xd4, 0xa2, 0xaf, 0x9c, 0xa4, 0x72, 0xc0, 0xb7, 0xfd, 0x93, 0x26, 0x36, 0x3f, 0xf7, 0xcc, 0x34, 0xa5, 0xe5, 0xf1, 0x71, 0xd8, 0x31, 0x15, 0x4, 0xc7, 0x23, 0xc3, 0x18, 0x96, 0x5, 0x9a, 0x7, 0x12, 0x80, 0xe2, 0xeb, 0x27, 0xb2, 0x75, 0x9, 0x83, 0x2c, 0x1a, 0x1b, 0x6e, 0x5a, 0xa0, 0x52, 0x3b, 0xd6, 0xb3, 0x29, 0xe3, 0x2f, 0x84, 0x53, 0xd1, 0x0, 0xed, 0x20, 0xfc, 0xb1, 0x5b, 0x6a, 0xcb, 0xbe, 0x39, 0x4a, 0x4c, 0x58, 0xcf, 0xd0, 0xef, 0xaa, 0xfb, 0x43, 0x4d, 0x33, 0x85, 0x45, 0xf9, 0x2, 0x7f, 0x50, 0x3c, 0x9f, 0xa8, 0x51, 0xa3, 0x40, 0x8f, 0x92, 0x9d, 0x38, 0xf5, 0xbc, 0xb6, 0xda, 0x21, 0x10, 0xff, 0xf3, 0xd2, 0xcd, 0xc, 0x13, 0xec, 0x5f, 0x97, 0x44, 0x17, 0xc4, 0xa7, 0x7e, 0x3d, 0x64, 0x5d, 0x19, 0x73, 0x60, 0x81, 0x4f, 0xdc, 0x22, 0x2a, 0x90, 0x88, 0x46, 0xee, 0xb8, 0x14, 0xde, 0x5e, 0xb, 0xdb, 0xe0, 0x32, 0x3a, 0xa, 0x49, 0x6, 0x24, 0x5c, 0xc2, 0xd3, 0xac, 0x62, 0x91, 0x95, 0xe4, 0x79, 0xe7, 0xc8, 0x37, 0x6d, 0x8d, 0xd5, 0x4e, 0xa9, 0x6c, 0x56, 0xf4, 0xea, 0x65, 0x7a, 0xae, 0x8, 0xba, 0x78, 0x25, 0x2e, 0x1c, 0xa6, 0xb4, 0xc6, 0xe8, 0xdd, 0x74, 0x1f, 0x4b, 0xbd, 0x8b, 0x8a, 0x70, 0x3e, 0xb5, 0x66, 0x48, 0x3, 0xf6, 0xe, 0x61, 0x35, 0x57, 0xb9, 0x86, 0xc1, 0x1d, 0x9e, 0xe1, 0xf8, 0x98, 0x11, 0x69, 0xd9, 0x8e, 0x94, 0x9b, 0x1e, 0x87, 0xe9, 0xce, 0x55, 0x28, 0xdf, 0x8c, 0xa1, 0x89, 0xd, 0xbf, 0xe6, 0x42, 0x68, 0x41, 0x99, 0x2d, 0xf, 0xb0, 0x54, 0xbb, 0x16),
            );
        }
        return $tables;
    }
    /**
     * Provides the inverse mixColumns and inverse sboxes tables
     *
     * @see self::_decryptBlock()
     * @see self::_setupInlineCrypt()
     * @see self::_setupKey()
     * @access private
     * @return array &$tables
     */
    function &_getInvTables()
    {
        static $tables;
        if (empty($tables)) {
            $dt3 = \array_map('intval', array(0xf4a75051, 0x4165537e, 0x17a4c31a, 0x275e963a, 0xab6bcb3b, 0x9d45f11f, 0xfa58abac, 0xe303934b, 0x30fa5520, 0x766df6ad, 0xcc769188, 0x24c25f5, 0xe5d7fc4f, 0x2acbd7c5, 0x35448026, 0x62a38fb5, 0xb15a49de, 0xba1b6725, 0xea0e9845, 0xfec0e15d, 0x2f7502c3, 0x4cf01281, 0x4697a38d, 0xd3f9c66b, 0x8f5fe703, 0x929c9515, 0x6d7aebbf, 0x5259da95, 0xbe832dd4, 0x7421d358, 0xe0692949, 0xc9c8448e, 0xc2896a75, 0x8e7978f4, 0x583e6b99, 0xb971dd27, 0xe14fb6be, 0x88ad17f0, 0x20ac66c9, 0xce3ab47d, 0xdf4a1863, 0x1a3182e5, 0x51336097, 0x537f4562, 0x6477e0b1, 0x6bae84bb, 0x81a01cfe, 0x82b94f9, 0x48685870, 0x45fd198f, 0xde6c8794, 0x7bf8b752, 0x73d323ab, 0x4b02e272, 0x1f8f57e3, 0x55ab2a66, 0xeb2807b2, 0xb5c2032f, 0xc57b9a86, 0x3708a5d3, 0x2887f230, 0xbfa5b223, 0x36aba02, 0x16825ced, 0xcf1c2b8a, 0x79b492a7, 0x7f2f0f3, 0x69e2a14e, 0xdaf4cd65, 0x5bed506, 0x34621fd1, 0xa6fe8ac4, 0x2e539d34, 0xf355a0a2, 0x8ae13205, 0xf6eb75a4, 0x83ec390b, 0x60efaa40, 0x719f065e, 0x6e1051bd, 0x218af93e, 0xdd063d96, 0x3e05aedd, 0xe6bd464d, 0x548db591, 0xc45d0571, 0x6d46f04, 0x5015ff60, 0x98fb2419, 0xbde997d6, 0x4043cc89, 0xd99e7767, 0xe842bdb0, 0x898b8807, 0x195b38e7, 0xc8eedb79, 0x7c0a47a1, 0x420fe97c, 0x841ec9f8, 0x0, 0x80868309, 0x2bed4832, 0x1170ac1e, 0x5a724e6c, 0xefffbfd, 0x8538560f, 0xaed51e3d, 0x2d392736, 0xfd9640a, 0x5ca62168, 0x5b54d19b, 0x362e3a24, 0xa67b10c, 0x57e70f93, 0xee96d2b4, 0x9b919e1b, 0xc0c54f80, 0xdc20a261, 0x774b695a, 0x121a161c, 0x93ba0ae2, 0xa02ae5c0, 0x22e0433c, 0x1b171d12, 0x90d0b0e, 0x8bc7adf2, 0xb6a8b92d, 0x1ea9c814, 0xf1198557, 0x75074caf, 0x99ddbbee, 0x7f60fda3, 0x1269ff7, 0x72f5bc5c, 0x663bc544, 0xfb7e345b, 0x4329768b, 0x23c6dccb, 0xedfc68b6, 0xe4f163b8, 0x31dccad7, 0x63851042, 0x97224013, 0xc6112084, 0x4a247d85, 0xbb3df8d2, 0xf93211ae, 0x29a16dc7, 0x9e2f4b1d, 0xb230f3dc, 0x8652ec0d, 0xc1e3d077, 0xb3166c2b, 0x70b999a9, 0x9448fa11, 0xe9642247, 0xfc8cc4a8, 0xf03f1aa0, 0x7d2cd856, 0x3390ef22, 0x494ec787, 0x38d1c1d9, 0xcaa2fe8c, 0xd40b3698, 0xf581cfa6, 0x7ade28a5, 0xb78e26da, 0xadbfa43f, 0x3a9de42c, 0x78920d50, 0x5fcc9b6a, 0x7e466254, 0x8d13c2f6, 0xd8b8e890, 0x39f75e2e, 0xc3aff582, 0x5d80be9f, 0xd0937c69, 0xd52da96f, 0x2512b3cf, 0xac993bc8, 0x187da710, 0x9c636ee8, 0x3bbb7bdb, 0x267809cd, 0x5918f46e, 0x9ab701ec, 0x4f9aa883, 0x956e65e6, 0xffe67eaa, 0xbccf0821, 0x15e8e6ef, 0xe79bd9ba, 0x6f36ce4a, 0x9f09d4ea, 0xb07cd629, 0xa4b2af31, 0x3f23312a, 0xa59430c6, 0xa266c035, 0x4ebc3774, 0x82caa6fc, 0x90d0b0e0, 0xa7d81533, 0x4984af1, 0xecdaf741, 0xcd500e7f, 0x91f62f17, 0x4dd68d76, 0xefb04d43, 0xaa4d54cc, 0x9604dfe4, 0xd1b5e39e, 0x6a881b4c, 0x2c1fb8c1, 0x65517f46, 0x5eea049d, 0x8c355d01, 0x877473fa, 0xb412efb, 0x671d5ab3, 0xdbd25292, 0x105633e9, 0xd647136d, 0xd7618c9a, 0xa10c7a37, 0xf8148e59, 0x133c89eb, 0xa927eece, 0x61c935b7, 0x1ce5ede1, 0x47b13c7a, 0xd2df599c, 0xf2733f55, 0x14ce7918, 0xc737bf73, 0xf7cdea53, 0xfdaa5b5f, 0x3d6f14df, 0x44db8678, 0xaff381ca, 0x68c43eb9, 0x24342c38, 0xa3405fc2, 0x1dc37216, 0xe2250cbc, 0x3c498b28, 0xd9541ff, 0xa8017139, 0xcb3de08, 0xb4e49cd8, 0x56c19064, 0xcb84617b, 0x32b670d5, 0x6c5c7448, 0xb85742d0));
            foreach ($dt3 as $dt3i) {
                $dt0[] = $dt3i << 24 & \intval(0xff000000) | $dt3i >> 8 & 0xffffff;
                $dt1[] = $dt3i << 16 & \intval(0xffff0000) | $dt3i >> 16 & 0xffff;
                $dt2[] = $dt3i << 8 & \intval(0xffffff00) | $dt3i >> 24 & 0xff;
            }
            $tables = array(
                // The Precomputed inverse mixColumns tables dt0 - dt3
                $dt0,
                $dt1,
                $dt2,
                $dt3,
                // The inverse SubByte S-Box
                array(0x52, 0x9, 0x6a, 0xd5, 0x30, 0x36, 0xa5, 0x38, 0xbf, 0x40, 0xa3, 0x9e, 0x81, 0xf3, 0xd7, 0xfb, 0x7c, 0xe3, 0x39, 0x82, 0x9b, 0x2f, 0xff, 0x87, 0x34, 0x8e, 0x43, 0x44, 0xc4, 0xde, 0xe9, 0xcb, 0x54, 0x7b, 0x94, 0x32, 0xa6, 0xc2, 0x23, 0x3d, 0xee, 0x4c, 0x95, 0xb, 0x42, 0xfa, 0xc3, 0x4e, 0x8, 0x2e, 0xa1, 0x66, 0x28, 0xd9, 0x24, 0xb2, 0x76, 0x5b, 0xa2, 0x49, 0x6d, 0x8b, 0xd1, 0x25, 0x72, 0xf8, 0xf6, 0x64, 0x86, 0x68, 0x98, 0x16, 0xd4, 0xa4, 0x5c, 0xcc, 0x5d, 0x65, 0xb6, 0x92, 0x6c, 0x70, 0x48, 0x50, 0xfd, 0xed, 0xb9, 0xda, 0x5e, 0x15, 0x46, 0x57, 0xa7, 0x8d, 0x9d, 0x84, 0x90, 0xd8, 0xab, 0x0, 0x8c, 0xbc, 0xd3, 0xa, 0xf7, 0xe4, 0x58, 0x5, 0xb8, 0xb3, 0x45, 0x6, 0xd0, 0x2c, 0x1e, 0x8f, 0xca, 0x3f, 0xf, 0x2, 0xc1, 0xaf, 0xbd, 0x3, 0x1, 0x13, 0x8a, 0x6b, 0x3a, 0x91, 0x11, 0x41, 0x4f, 0x67, 0xdc, 0xea, 0x97, 0xf2, 0xcf, 0xce, 0xf0, 0xb4, 0xe6, 0x73, 0x96, 0xac, 0x74, 0x22, 0xe7, 0xad, 0x35, 0x85, 0xe2, 0xf9, 0x37, 0xe8, 0x1c, 0x75, 0xdf, 0x6e, 0x47, 0xf1, 0x1a, 0x71, 0x1d, 0x29, 0xc5, 0x89, 0x6f, 0xb7, 0x62, 0xe, 0xaa, 0x18, 0xbe, 0x1b, 0xfc, 0x56, 0x3e, 0x4b, 0xc6, 0xd2, 0x79, 0x20, 0x9a, 0xdb, 0xc0, 0xfe, 0x78, 0xcd, 0x5a, 0xf4, 0x1f, 0xdd, 0xa8, 0x33, 0x88, 0x7, 0xc7, 0x31, 0xb1, 0x12, 0x10, 0x59, 0x27, 0x80, 0xec, 0x5f, 0x60, 0x51, 0x7f, 0xa9, 0x19, 0xb5, 0x4a, 0xd, 0x2d, 0xe5, 0x7a, 0x9f, 0x93, 0xc9, 0x9c, 0xef, 0xa0, 0xe0, 0x3b, 0x4d, 0xae, 0x2a, 0xf5, 0xb0, 0xc8, 0xeb, 0xbb, 0x3c, 0x83, 0x53, 0x99, 0x61, 0x17, 0x2b, 0x4, 0x7e, 0xba, 0x77, 0xd6, 0x26, 0xe1, 0x69, 0x14, 0x63, 0x55, 0x21, 0xc, 0x7d),
            );
        }
        return $tables;
    }
    /**
     * Setup the performance-optimized function for de/encrypt()
     *
     * @see \phpseclib\Crypt\Base::_setupInlineCrypt()
     * @access private
     */
    function _setupInlineCrypt()
    {
        // Note: _setupInlineCrypt() will be called only if $this->changed === true
        // So here we are'nt under the same heavy timing-stress as we are in _de/encryptBlock() or de/encrypt().
        // However...the here generated function- $code, stored as php callback in $this->inline_crypt, must work as fast as even possible.
        $lambda_functions =& self::_getLambdaFunctions();
        // We create max. 10 hi-optimized code for memory reason. Means: For each $key one ultra fast inline-crypt function.
        // (Currently, for Crypt_Rijndael/AES, one generated $lambda_function cost on php5.5@32bit ~80kb unfreeable mem and ~130kb on php5.5@64bit)
        // After that, we'll still create very fast optimized code but not the hi-ultimative code, for each $mode one.
        $gen_hi_opt_code = (bool) (\count($lambda_functions) < 10);
        // Generation of a uniqe hash for our generated code
        $code_hash = "Crypt_Rijndael, {$this->mode}, {$this->Nr}, {$this->Nb}";
        if ($gen_hi_opt_code) {
            $code_hash = \str_pad($code_hash, 32) . $this->_hashInlineCryptFunction($this->key);
        }
        if (!isset($lambda_functions[$code_hash])) {
            switch (\true) {
                case $gen_hi_opt_code:
                    // The hi-optimized $lambda_functions will use the key-words hardcoded for better performance.
                    $w = $this->w;
                    $dw = $this->dw;
                    $init_encrypt = '';
                    $init_decrypt = '';
                    break;
                default:
                    for ($i = 0, $cw = \count($this->w); $i < $cw; ++$i) {
                        $w[] = '$w[' . $i . ']';
                        $dw[] = '$dw[' . $i . ']';
                    }
                    $init_encrypt = '$w  = $self->w;';
                    $init_decrypt = '$dw = $self->dw;';
            }
            $Nr = $this->Nr;
            $Nb = $this->Nb;
            $c = $this->c;
            // Generating encrypt code:
            $init_encrypt .= '
                static $tables;
                if (empty($tables)) {
                    $tables = &$self->_getTables();
                }
                $t0   = $tables[0];
                $t1   = $tables[1];
                $t2   = $tables[2];
                $t3   = $tables[3];
                $sbox = $tables[4];
            ';
            $s = 'e';
            $e = 's';
            $wc = $Nb - 1;
            // Preround: addRoundKey
            $encrypt_block = '$in = unpack("N*", $in);' . "\n";
            for ($i = 0; $i < $Nb; ++$i) {
                $encrypt_block .= '$s' . $i . ' = $in[' . ($i + 1) . '] ^ ' . $w[++$wc] . ";\n";
            }
            // Mainrounds: shiftRows + subWord + mixColumns + addRoundKey
            for ($round = 1; $round < $Nr; ++$round) {
                list($s, $e) = array($e, $s);
                for ($i = 0; $i < $Nb; ++$i) {
                    $encrypt_block .= '$' . $e . $i . ' =
                        $t0[($' . $s . $i . ' >> 24) & 0xff] ^
                        $t1[($' . $s . ($i + $c[1]) % $Nb . ' >> 16) & 0xff] ^
                        $t2[($' . $s . ($i + $c[2]) % $Nb . ' >>  8) & 0xff] ^
                        $t3[ $' . $s . ($i + $c[3]) % $Nb . '        & 0xff] ^
                        ' . $w[++$wc] . ";\n";
                }
            }
            // Finalround: subWord + shiftRows + addRoundKey
            for ($i = 0; $i < $Nb; ++$i) {
                $encrypt_block .= '$' . $e . $i . ' =
                     $sbox[ $' . $e . $i . '        & 0xff]        |
                    ($sbox[($' . $e . $i . ' >>  8) & 0xff] <<  8) |
                    ($sbox[($' . $e . $i . ' >> 16) & 0xff] << 16) |
                    ($sbox[($' . $e . $i . ' >> 24) & 0xff] << 24);' . "\n";
            }
            $encrypt_block .= '$in = pack("N*"' . "\n";
            for ($i = 0; $i < $Nb; ++$i) {
                $encrypt_block .= ',
                    ($' . $e . $i . ' & ' . (int) 0xff000000 . ') ^
                    ($' . $e . ($i + $c[1]) % $Nb . ' &         0x00FF0000   ) ^
                    ($' . $e . ($i + $c[2]) % $Nb . ' &         0x0000FF00   ) ^
                    ($' . $e . ($i + $c[3]) % $Nb . ' &         0x000000FF   ) ^
                    ' . $w[$i] . "\n";
            }
            $encrypt_block .= ');';
            // Generating decrypt code:
            $init_decrypt .= '
                static $invtables;
                if (empty($invtables)) {
                    $invtables = &$self->_getInvTables();
                }
                $dt0   = $invtables[0];
                $dt1   = $invtables[1];
                $dt2   = $invtables[2];
                $dt3   = $invtables[3];
                $isbox = $invtables[4];
            ';
            $s = 'e';
            $e = 's';
            $wc = $Nb - 1;
            // Preround: addRoundKey
            $decrypt_block = '$in = unpack("N*", $in);' . "\n";
            for ($i = 0; $i < $Nb; ++$i) {
                $decrypt_block .= '$s' . $i . ' = $in[' . ($i + 1) . '] ^ ' . $dw[++$wc] . ';' . "\n";
            }
            // Mainrounds: shiftRows + subWord + mixColumns + addRoundKey
            for ($round = 1; $round < $Nr; ++$round) {
                list($s, $e) = array($e, $s);
                for ($i = 0; $i < $Nb; ++$i) {
                    $decrypt_block .= '$' . $e . $i . ' =
                        $dt0[($' . $s . $i . ' >> 24) & 0xff] ^
                        $dt1[($' . $s . ($Nb + $i - $c[1]) % $Nb . ' >> 16) & 0xff] ^
                        $dt2[($' . $s . ($Nb + $i - $c[2]) % $Nb . ' >>  8) & 0xff] ^
                        $dt3[ $' . $s . ($Nb + $i - $c[3]) % $Nb . '        & 0xff] ^
                        ' . $dw[++$wc] . ";\n";
                }
            }
            // Finalround: subWord + shiftRows + addRoundKey
            for ($i = 0; $i < $Nb; ++$i) {
                $decrypt_block .= '$' . $e . $i . ' =
                     $isbox[ $' . $e . $i . '        & 0xff]        |
                    ($isbox[($' . $e . $i . ' >>  8) & 0xff] <<  8) |
                    ($isbox[($' . $e . $i . ' >> 16) & 0xff] << 16) |
                    ($isbox[($' . $e . $i . ' >> 24) & 0xff] << 24);' . "\n";
            }
            $decrypt_block .= '$in = pack("N*"' . "\n";
            for ($i = 0; $i < $Nb; ++$i) {
                $decrypt_block .= ',
                    ($' . $e . $i . ' & ' . (int) 0xff000000 . ') ^
                    ($' . $e . ($Nb + $i - $c[1]) % $Nb . ' &         0x00FF0000   ) ^
                    ($' . $e . ($Nb + $i - $c[2]) % $Nb . ' &         0x0000FF00   ) ^
                    ($' . $e . ($Nb + $i - $c[3]) % $Nb . ' &         0x000000FF   ) ^
                    ' . $dw[$i] . "\n";
            }
            $decrypt_block .= ');';
            $lambda_functions[$code_hash] = $this->_createInlineCryptFunction(array('init_crypt' => '', 'init_encrypt' => $init_encrypt, 'init_decrypt' => $init_decrypt, 'encrypt_block' => $encrypt_block, 'decrypt_block' => $decrypt_block));
        }
        $this->inline_crypt = $lambda_functions[$code_hash];
    }
}
