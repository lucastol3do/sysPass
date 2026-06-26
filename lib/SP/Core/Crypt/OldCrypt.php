<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2019, Rubén Domínguez nuxsmin@$syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Core\Crypt;

use SP\Core\Exceptions\SPException;

defined('APP_ROOT') || die();

/**
 * Class OldCrypt - Legacy encryption using mcrypt extension (REMOVED)
 *
 * This class previously used the mcrypt PHP extension which was deprecated
 * in PHP 7.1 and removed in PHP 7.2. It was used for decrypting data from
 * sysPass versions prior to 2.1.
 *
 * @deprecated Since 2.1 - mcrypt extension removed in PHP 7.2+
 * @see Crypt Use the modern Crypt class with defuse/php-encryption instead
 */
final class OldCrypt
{
    /**
     * Decrypt data using the legacy mcrypt algorithm (RIJNDAEL_256/CBC).
     *
     * This method is NO LONGER FUNCTIONAL because the mcrypt extension
     * was removed in PHP 7.2. It will always throw an exception.
     *
     * @param string $cryptData The encrypted data
     * @param string $cryptIV   The initialization vector
     * @param string $password  The decryption key
     *
     * @return string
     * @throws SPException Always throws - mcrypt is no longer available
     */
    public static function getDecrypt($cryptData, $cryptIV, $password)
    {
        throw new SPException(
            __u('Legacy decryption is not supported'),
            SPException::ERROR,
            __u('The mcrypt extension was removed in PHP 7.2. Please use the import tool from sysPass 2.x to migrate your data, or import from a version >= 2.10 which uses the modern encryption system.')
        );
    }

    /**
     * Check if the mcrypt module is available.
     *
     * @return bool Always returns false - mcrypt is no longer available
     */
    public static function checkCryptModule()
    {
        return false;
    }
}