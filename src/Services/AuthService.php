<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Http\ApiException;
use App\Http\SeriesApiClient;

final class AuthService
{
    /**
     * Validate key code from SQLite/MySQL and authorize with SeriesJeen API using server secret.
     * @return array{ok:bool, user?:array, platforms?:array, role?:string, error?:string}
     */
    public function validateKey(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['ok' => false, 'error' => 'กรุณากรอกรหัสเข้าใช้งาน'];
        }

        // 1. Check code in MySQL database
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT * FROM `key_codes` WHERE `code` = :code LIMIT 1");
            $stmt->execute(['code' => $code]);
            $row = $stmt->fetch();
        } catch (\Throwable $e) {
            error_log('[AuthService] DB check failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage()];
        }

        if (!$row) {
            return ['ok' => false, 'error' => 'รหัสเข้าใช้งานไม่ถูกต้อง'];
        }

        // 2. Check if expired
        if ($row['expires_at'] !== null) {
            $expiry = strtotime($row['expires_at']);
            if ($expiry < time()) {
                return ['ok' => false, 'error' => 'รหัสเข้าใช้งานนี้หมดอายุการใช้งานแล้ว'];
            }
        }

        // 2.5. HWID / Device locking (Only for 'user' role)
        if ($row['role'] === 'user') {
            $currentHwidCookie = $_COOKIE['device_hwid'] ?? '';
            $userAgentStr = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $this->getClientIp();

            if ($row['hwid'] === null) {
                // First-time login: bind device
                $newHwid = $this->generateUuidV7();
                try {
                    $stmtUpdate = $db->prepare("UPDATE `key_codes` SET `hwid` = :hwid, `last_active_at` = :now, `user_agent` = :ua, `ip_address` = :ip WHERE `id` = :id");
                    $stmtUpdate->execute([
                        'hwid' => $newHwid,
                        'now' => date('Y-m-d H:i:s'),
                        'ua' => $userAgentStr,
                        'ip' => $ipAddress,
                        'id' => $row['id']
                    ]);
                    
                    // Set secure HttpOnly cookie for 1 year (365 days)
                    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                    setcookie('device_hwid', $newHwid, [
                        'expires' => time() + 31536000,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $isSecure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                } catch (\Throwable $e) {
                    error_log('[AuthService] Failed to bind HWID: ' . $e->getMessage());
                }
            } else {
                // Key is already locked to a device
                if ($currentHwidCookie === '' || $currentHwidCookie !== $row['hwid']) {
                    return [
                        'ok' => false,
                        'error' => 'รหัสเข้าใช้งานนี้ถูกจำกัดให้ใช้ได้เพียง 1 อุปกรณ์ และได้ล็อกใช้งานกับเครื่องอื่นไปแล้ว หากต้องการย้ายเครื่อง กรุณาติดต่อผู้ดูแลระบบเพื่อรีเซ็ตอุปกรณ์'
                    ];
                }

                // Check for User-Agent platform mismatch to prevent copied/cloned cookies
                $dbParsedUa = $this->parseUserAgent($row['user_agent'] ?? '');
                $currentParsedUa = $this->parseUserAgent($userAgentStr);
                
                $dbParts = explode(' on ', $dbParsedUa);
                $currParts = explode(' on ', $currentParsedUa);
                $dbOs = $dbParts[1] ?? '';
                $currOs = $currParts[1] ?? '';
                
                if ($dbOs !== '' && $currOs !== '' && $dbOs !== $currOs) {
                    return [
                        'ok' => false,
                        'error' => 'ตรวจพบการย้ายบราวเซอร์หรือระบบปฏิบัติการต่างเครื่องอย่างผิดปกติ เพื่อความปลอดภัยระบบได้ระงับการเข้าสู่ระบบนี้ กรุณาติดต่อผู้ดูแลระบบ'
                    ];
                }

                // Update activity details
                try {
                    $stmtUpdate = $db->prepare("UPDATE `key_codes` SET `last_active_at` = :now, `ip_address` = :ip, `user_agent` = :ua WHERE `id` = :id");
                    $stmtUpdate->execute([
                        'now' => date('Y-m-d H:i:s'),
                        'ip' => $ipAddress,
                        'ua' => $userAgentStr,
                        'id' => $row['id']
                    ]);
                } catch (\Throwable $e) {
                    error_log('[AuthService] Failed to update active HWID stats: ' . $e->getMessage());
                }
            }
        }

        // 3. Verify server-side secret SeriesJeen API key is set
        $serverApiKey = trim($_ENV['SERIES_API_KEY'] ?? '');
        if ($serverApiKey === 'mock') {
            return [
                'ok' => true,
                'role' => (string)$row['role'],
                'user' => [
                    'id' => 9999,
                    'name' => 'Mock Developer',
                    'email' => 'mock@seriesjeen.online',
                    'avatar_url' => null,
                    'usage_today' => 0,
                ],
                'platforms' => [
                    [
                        'slug' => 'dramabox',
                        'name' => 'DramaBox',
                        'platform_id' => 1,
                        'image' => '/public/assets/mock_cover.png',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'days_remaining' => 30,
                    ],
                    [
                        'slug' => 'shortmax',
                        'name' => 'ShortMax',
                        'platform_id' => 2,
                        'image' => '/public/assets/mock_cover.png',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'days_remaining' => 30,
                    ],
                    [
                        'slug' => 'reelshort',
                        'name' => 'ReelShort',
                        'platform_id' => 3,
                        'image' => '/public/assets/mock_cover.png',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'days_remaining' => 30,
                    ]
                ],
            ];
        }

        if ($serverApiKey === '') {
            return [
                'ok' => false, 
                'error' => 'เซิร์ฟเวอร์ยังไม่ได้ตั้งค่า SERIES_API_KEY กรุณาแจ้งผู้ดูแลระบบเพื่อระบุคีย์ใน .env'
            ];
        }

        // 4. Query SeriesJeen API using the hidden secret key to check platform access
        $client = new SeriesApiClient($serverApiKey);

        try {
            $me = $client->getJson('/api/me');
        } catch (ApiException $e) {
            if ($e->httpStatus === 401) {
                return ['ok' => false, 'error' => 'SERIES_API_KEY ของเซิร์ฟเวอร์ไม่ถูกต้องหรือถูกระงับ'];
            }
            return ['ok' => false, 'error' => 'เกิดข้อผิดพลาดในการตรวจสอบคีย์กับ API: ' . $e->getMessage()];
        }

        if (!($me['is_active'] ?? false)) {
            return ['ok' => false, 'error' => 'บัญชี API หลักของเซิร์ฟเวอร์ถูกปิดใช้งาน'];
        }

        try {
            $accessRaw = $client->getJson('/api/me/access');
        } catch (ApiException $e) {
            return ['ok' => false, 'error' => 'ดึงรายการแพลตฟอร์มไม่สำเร็จ: ' . $e->getMessage()];
        }

        $list = $accessRaw['platforms'] ?? (array_is_list($accessRaw) ? $accessRaw : []);

        $seen = [];
        foreach ($list as $rowPlatform) {
            if (!is_array($rowPlatform)) continue;
            $name = $rowPlatform['name'] ?? null;
            if (!$name) continue;
            $slug = strtolower((string)$name);
            $days = (int)($rowPlatform['days_remaining'] ?? 0);
            
            // Fix bug: Check actual expiration using expires_at instead of strictly days_remaining <= 0.
            // When user has < 24 hours left, days_remaining is 0 but expires_at is still in the future.
            $expiresAtStr = $rowPlatform['expires_at'] ?? '';
            if ($expiresAtStr !== '') {
                $expiry = strtotime($expiresAtStr);
                if ($expiry !== false) {
                    if ($expiry < time()) {
                        continue; // Expired
                    }
                    if ($days <= 0) {
                        $days = 1; // Show at least 1 day remaining if still in future
                    }
                }
            } else {
                if ($days <= 0) continue;
            }

            $normalized = [
                'slug' => $slug,
                'name' => (string)$name,
                'platform_id' => $rowPlatform['platform_id'] ?? null,
                'image' => $rowPlatform['image'] ?? null,
                'expires_at' => $rowPlatform['expires_at'] ?? null,
                'days_remaining' => $days,
            ];

            if (!isset($seen[$slug]) || $days > $seen[$slug]['days_remaining']) {
                $seen[$slug] = $normalized;
            }
        }
        $platforms = array_values($seen);
        usort($platforms, fn($a, $b) => strcmp($a['slug'], $b['slug']));

        if (empty($platforms) && ($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $platforms = [
                [
                    'slug' => 'dramabox',
                    'name' => 'DramaBox (Mock)',
                    'platform_id' => 1,
                    'image' => '/public/assets/mock_cover.png',
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+365 days')),
                    'days_remaining' => 365,
                ],
                [
                    'slug' => 'shortmax',
                    'name' => 'ShortMax (Mock)',
                    'platform_id' => 2,
                    'image' => '/public/assets/mock_cover.png',
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+365 days')),
                    'days_remaining' => 365,
                ],
                [
                    'slug' => 'reelshort',
                    'name' => 'ReelShort (Mock)',
                    'platform_id' => 3,
                    'image' => '/public/assets/mock_cover.png',
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+365 days')),
                    'days_remaining' => 365,
                ]
            ];
        }

        return [
            'ok' => true,
            'role' => (string)$row['role'],
            'user' => [
                'id' => $me['id'] ?? null,
                'name' => $me['name'] ?? '',
                'email' => $me['email'] ?? '',
                'avatar_url' => $me['avatar_url'] ?? null,
                'usage_today' => $me['usage_today'] ?? null,
            ],
            'platforms' => $platforms,
        ];
    }

    private function getClientIp(): string
    {
        return $_SERVER['HTTP_CF_CONNECTING_IP'] 
            ?? $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '127.0.0.1';
    }

    private function parseUserAgent(string $ua): string
    {
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';

        if (preg_match('/iphone|ipad|ipod/i', $ua)) {
            $os = 'iOS';
        } elseif (preg_match('/android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/windows|win32/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        if (preg_match('/chrome|crios/i', $ua) && !preg_match('/opr|opios|edge|edg/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome|crios|android/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/firefox|fxios/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/edge|edg/i', $ua)) {
            $browser = 'Edge';
        } elseif (preg_match('/opera|opr|opios/i', $ua)) {
            $browser = 'Opera';
        }

        return "$browser on $os";
    }

    private function generateUuidV7(): string
    {
        $milliTime = (int)floor(microtime(true) * 1000);
        $timestampHex = str_pad(dechex($milliTime), 12, '0', STR_PAD_LEFT);
        
        $part1 = substr($timestampHex, 0, 8);
        $part2 = substr($timestampHex, 8, 4);
        
        $part3 = '7' . str_pad(dechex(random_int(0, 0x0fff)), 3, '0', STR_PAD_LEFT);
        
        $variantAndRand = (random_int(0, 0xffff) & 0x3fff) | 0x8000;
        $part4 = str_pad(dechex($variantAndRand), 4, '0', STR_PAD_LEFT);
        
        $part5 = str_pad(dechex(random_int(0, 0xffff)), 4, '0', STR_PAD_LEFT) .
                 str_pad(dechex(random_int(0, 0xffff)), 4, '0', STR_PAD_LEFT) .
                 str_pad(dechex(random_int(0, 0xffff)), 4, '0', STR_PAD_LEFT);
                 
        return sprintf('%s-%s-%s-%s-%s', $part1, $part2, $part3, $part4, $part5);
    }
}
