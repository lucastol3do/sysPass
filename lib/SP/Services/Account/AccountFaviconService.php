<?php
declare(strict_types=1);

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

namespace SP\Services\Account;

use Exception;
use GuzzleHttp\Client;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SP\Core\Exceptions\ConstraintException;
use SP\Core\Exceptions\QueryException;
use SP\DataModel\AccountVData;
use SP\Repositories\NoSuchItemException;
use SP\Services\Service;
use SP\Storage\File\FileException;
use SP\Storage\File\FileHandler;

defined('APP_ROOT') || die();

/**
 * Class AccountFaviconService
 *
 * Service to fetch and cache favicons for accounts.
 *
 * @package SP\Services\Account
 */
final class AccountFaviconService extends Service
{
    const FAVICON_DIR = CACHE_PATH . DIRECTORY_SEPARATOR . 'favicons';
    const DEFAULT_FAVICON = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * @var AccountService
     */
    protected $accountService;

    /**
     * @var Client|null
     */
    protected $httpClient;

    /**
     * Get the web-accessible favicon URL for an account.
     * Fetches and caches the favicon if not already stored.
     *
     * @param int $accountId
     *
     * @return string URL to the favicon (web route or data URI for default)
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ConstraintException
     * @throws QueryException
     */
    public function getFaviconUrl(int $accountId): string
    {
        $faviconFile = $this->getFaviconPath($accountId);

        if (file_exists($faviconFile) && filesize($faviconFile) > 0) {
            return 'index.php?r=account/favicon&accountId=' . $accountId;
        }

        try {
            $this->fetchAndStore($accountId);

            if (file_exists($faviconFile) && filesize($faviconFile) > 0) {
                return 'index.php?r=account/favicon&accountId=' . $accountId;
            }
        } catch (Exception $e) {
            processException($e);
        }

        return self::DEFAULT_FAVICON;
    }

    /**
     * Fetch favicon from the account's URL and store it locally.
     *
     * @param int $accountId
     *
     * @return bool True if favicon was fetched and stored successfully
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ConstraintException
     * @throws QueryException
     */
    public function fetchAndStore(int $accountId): bool
    {
        $accountData = $this->getAccountData($accountId);

        if (empty($accountData) || empty($accountData->getUrl())) {
            return false;
        }

        $faviconData = $this->fetchFaviconFromUrl($accountData->getUrl());

        if ($faviconData === null || empty($faviconData)) {
            return false;
        }

        try {
            $this->ensureFaviconDir();

            $fileHandler = new FileHandler($this->getFaviconPath($accountId));
            $fileHandler->save($faviconData);

            return true;
        } catch (FileException $e) {
            processException($e);
        }

        return false;
    }

    /**
     * Fetch favicon from a URL using multiple strategies.
     *
     * @param string $url
     *
     * @return string|null Raw favicon data, or null on failure
     */
    public function fetchFaviconFromUrl(string $url): ?string
    {
        $domain = $this->extractDomain($url);

        if ($domain === null) {
            return null;
        }

        $httpClient = $this->getHttpClient();

        if ($httpClient === null) {
            return $this->fetchWithFileGetContents($domain, $url);
        }

        // Strategy 1: Try /favicon.ico directly
        try {
            $response = $httpClient->get('https://' . $domain . '/favicon.ico', [
                'timeout' => 5,
                'allow_redirects' => true,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() === 200
                && $response->getBody()->getSize() > 0
                && $this->isValidIconContent($response->getBody()->getContents())
            ) {
                return $response->getBody()->getContents();
            }
        } catch (Exception $e) {
            processException($e);
        }

        // Strategy 2: Parse HTML for <link rel="icon">
        try {
            $response = $httpClient->get('https://' . $domain, [
                'timeout' => 5,
                'allow_redirects' => true,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; sysPass)',
                ],
            ]);

            if ($response->getStatusCode() === 200) {
                $html = (string)$response->getBody();
                $iconUrl = $this->extractIconFromHtml($html, $domain);

                if ($iconUrl !== null) {
                    try {
                        $iconResponse = $httpClient->get($iconUrl, [
                            'timeout' => 5,
                            'allow_redirects' => true,
                            'http_errors' => false,
                        ]);

                        if ($iconResponse->getStatusCode() === 200
                            && $iconResponse->getBody()->getSize() > 0
                            && $this->isValidIconContent($iconResponse->getBody()->getContents())
                        ) {
                            return $iconResponse->getBody()->getContents();
                        }
                    } catch (Exception $e) {
                        processException($e);
                    }
                }
            }
        } catch (Exception $e) {
            processException($e);
        }

        // Strategy 3: Google favicon API as fallback
        try {
            $googleUrl = 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=32';
            $response = $httpClient->get($googleUrl, [
                'timeout' => 5,
                'allow_redirects' => true,
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() === 200
                && $response->getBody()->getSize() > 0
                && $this->isValidIconContent($response->getBody()->getContents())
            ) {
                return $response->getBody()->getContents();
            }
        } catch (Exception $e) {
            processException($e);
        }

        return null;
    }

    /**
     * Fallback fetch using file_get_contents when Guzzle is not available.
     *
     * @param string $domain
     * @param string $originalUrl
     *
     * @return string|null
     */
    private function fetchWithFileGetContents(string $domain, string $originalUrl): ?string
    {
        // Strategy 1: Try /favicon.ico
        $faviconUrl = 'https://' . $domain . '/favicon.ico';
        $data = @file_get_contents($faviconUrl, false, stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]));

        if ($data !== false && strlen($data) > 0 && $this->isValidIconContent($data)) {
            return $data;
        }

        // Strategy 2: Try Google fallback
        $googleUrl = 'https://www.google.com/s2/favicons?domain=' . urlencode($domain) . '&sz=32';
        $data = @file_get_contents($googleUrl, false, stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]));

        if ($data !== false && strlen($data) > 0 && $this->isValidIconContent($data)) {
            return $data;
        }

        return null;
    }

    /**
     * Clear the favicon cache for one or all accounts.
     *
     * @param int|null $accountId If set, clear only this account's favicon
     *
     * @return bool
     */
    public function clearCache(int $accountId = null): bool
    {
        try {
            if ($accountId !== null) {
                $faviconFile = $this->getFaviconPath($accountId);

                if (file_exists($faviconFile)) {
                    $fileHandler = new FileHandler($faviconFile);
                    $fileHandler->delete();

                    return true;
                }

                return false;
            }

            // Clear all favicons
            $faviconDir = self::FAVICON_DIR;

            if (is_dir($faviconDir)) {
                $files = glob($faviconDir . DIRECTORY_SEPARATOR . '*.ico');

                if ($files !== false) {
                    foreach ($files as $file) {
                        @unlink($file);
                    }
                }

                $files = glob($faviconDir . DIRECTORY_SEPARATOR . '*.png');

                if ($files !== false) {
                    foreach ($files as $file) {
                        @unlink($file);
                    }
                }
            }

            return true;
        } catch (Exception $e) {
            processException($e);
        }

        return false;
    }

    /**
     * Get the favicon file path for an account.
     *
     * @param int $accountId
     *
     * @return string
     */
    public function getFaviconPath(int $accountId): string
    {
        return self::FAVICON_DIR . DIRECTORY_SEPARATOR . $accountId . '.ico';
    }

    /**
     * Read the favicon file content for an account.
     *
     * @param int $accountId
     *
     * @return string|null
     */
    public function readFavicon(int $accountId): ?string
    {
        $faviconFile = $this->getFaviconPath($accountId);

        if (!file_exists($faviconFile) || filesize($faviconFile) === 0) {
            return null;
        }

        try {
            $fileHandler = new FileHandler($faviconFile);
            return $fileHandler->readToString();
        } catch (FileException $e) {
            processException($e);
        }

        return null;
    }

    /**
     * Get the MIME type of the stored favicon.
     *
     * @param int $accountId
     *
     * @return string
     */
    public function getFaviconMimeType(int $accountId): string
    {
        $faviconFile = $this->getFaviconPath($accountId);

        if (!file_exists($faviconFile)) {
            return 'image/png';
        }

        try {
            $fileHandler = new FileHandler($faviconFile);
            return $fileHandler->getFileType();
        } catch (FileException $e) {
            processException($e);
        }

        return 'image/png';
    }

    /**
     * Extract the domain from a URL.
     *
     * @param string $url
     *
     * @return string|null
     */
    private function extractDomain(string $url): ?string
    {
        $url = trim($url);

        if (empty($url)) {
            return null;
        }

        // Add scheme if missing
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);

        if (!isset($parts['host'])) {
            return null;
        }

        return $parts['host'];
    }

    /**
     * Extract icon URL from HTML content.
     *
     * @param string $html
     * @param string $domain
     *
     * @return string|null
     */
    private function extractIconFromHtml(string $html, string $domain): ?string
    {
        // Try to find <link rel="icon" ...> or <link rel="shortcut icon" ...>
        $pattern = '/<link\s+[^>]*rel=["\'](?:shortcut\s+)?icon["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i';

        if (preg_match($pattern, $html, $matches)) {
            $iconUrl = $matches[1];

            // Handle relative URLs
            if (strpos($iconUrl, '://') === false) {
                if (strpos($iconUrl, '/') === 0) {
                    $iconUrl = 'https://' . $domain . $iconUrl;
                } else {
                    $iconUrl = 'https://' . $domain . '/' . $iconUrl;
                }
            }

            return $iconUrl;
        }

        // Try alternate pattern with href before rel
        $pattern2 = '/<link\s+[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\'](?:shortcut\s+)?icon["\'][^>]*>/i';

        if (preg_match($pattern2, $html, $matches)) {
            $iconUrl = $matches[1];

            if (strpos($iconUrl, '://') === false) {
                if (strpos($iconUrl, '/') === 0) {
                    $iconUrl = 'https://' . $domain . $iconUrl;
                } else {
                    $iconUrl = 'https://' . $domain . '/' . $iconUrl;
                }
            }

            return $iconUrl;
        }

        return null;
    }

    /**
     * Check if the fetched content looks like a valid icon/image.
     *
     * @param string $data
     *
     * @return bool
     */
    private function isValidIconContent(string $data): bool
    {
        if (empty($data)) {
            return false;
        }

        // Check for common image magic bytes
        $firstBytes = substr($data, 0, 4);

        // ICO magic bytes: 00 00 01 00
        if (substr($data, 0, 4) === "\x00\x00\x01\x00") {
            return true;
        }

        // PNG magic bytes: 89 50 4E 47
        if (substr($data, 0, 4) === "\x89\x50\x4E\x47") {
            return true;
        }

        // GIF magic bytes: 47 49 46 38
        if (substr($data, 0, 3) === "GIF") {
            return true;
        }

        // JPEG magic bytes: FF D8 FF
        if (substr($data, 0, 3) === "\xFF\xD8\xFF") {
            return true;
        }

        // SVG: starts with <svg or <?xml
        if (strpos($data, '<svg') !== false || strpos($data, '<?xml') !== false) {
            return true;
        }

        // WebP: 52 49 46 46 (RIFF)
        if (substr($data, 0, 4) === "RIFF") {
            return true;
        }

        return false;
    }

    /**
     * Ensure the favicon cache directory exists.
     *
     * @throws FileException
     */
    private function ensureFaviconDir(): void
    {
        $faviconDir = self::FAVICON_DIR;

        if (!is_dir($faviconDir)) {
            if (@mkdir($faviconDir, 0755, true) === false && !is_dir($faviconDir)) {
                throw new FileException(
                    sprintf(__('Unable to create the directory (%s)'), $faviconDir)
                );
            }
        }
    }

    /**
     * Get the account data for the given ID.
     *
     * @param int $accountId
     *
     * @return AccountVData|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ConstraintException
     * @throws QueryException
     */
    private function getAccountData(int $accountId): ?AccountVData
    {
        try {
            $accountDetails = $this->accountService->getById($accountId);

            return $accountDetails->getAccountVData();
        } catch (NoSuchItemException $e) {
            processException($e);
        }

        return null;
    }

    /**
     * Get or create the HTTP client.
     *
     * @return Client|null
     */
    private function getHttpClient(): ?Client
    {
        if ($this->httpClient === null) {
            try {
                if (class_exists(Client::class)) {
                    $this->httpClient = new Client([
                        'timeout' => 10,
                        'verify' => false,
                    ]);
                }
            } catch (Exception $e) {
                processException($e);
            }
        }

        return $this->httpClient;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function initialize()
    {
        $this->accountService = $this->dic->get(AccountService::class);
    }
}
