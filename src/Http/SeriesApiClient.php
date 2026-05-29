<?php
declare(strict_types=1);

namespace App\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

final class SeriesApiClient
{
    private Client $client;

    public function __construct(
        private readonly string $apiKey,
        ?string $baseUri = null,
        ?string $userAgent = null,
        ?int $timeoutSeconds = null,
    ) {
        $baseUri ??= $_ENV['SERIES_API_BASE_URL'] ?? 'https://api.seriesjeen.online';
        $userAgent ??= $_ENV['SERIES_API_USER_AGENT']
            ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $timeoutSeconds ??= (int)($_ENV['SERIES_API_TIMEOUT'] ?? 15);

        $this->client = new Client([
            'base_uri' => $baseUri,
            'timeout'  => $timeoutSeconds,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'User-Agent'    => $userAgent,
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /** @return array<string,mixed> */
    public function getJson(string $path, array $query = []): array
    {
        if ($this->apiKey === 'mock' || $this->apiKey === '') {
            return $this->handleMockRequest($path, $query);
        }

        try {
            $response = $this->client->request('GET', $path, ['query' => $query]);
            $status = $response->getStatusCode();
            $body = (string)$response->getBody();
            $decoded = json_decode($body, true);

            if ($status >= 400) {
                // If it fails with auth/permission issues and we are in debug mode, fallback to mock!
                if (($status === 401 || $status === 403 || $status === 404) && ($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                    error_log("[SeriesApiClient] Upstream returned $status. Falling back to mock data because APP_DEBUG=true.");
                    return $this->handleMockRequest($path, $query);
                }
                $msg = is_array($decoded) && isset($decoded['detail'])
                    ? (is_string($decoded['detail']) ? $decoded['detail'] : json_encode($decoded['detail']))
                    : 'HTTP ' . $status;
                throw new ApiException($msg, $status, is_array($decoded) ? $decoded : null);
            }

            if (!is_array($decoded)) {
                throw new ApiException('Invalid JSON response', 502);
            }

            return $decoded;
        } catch (GuzzleException $e) {
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                error_log("[SeriesApiClient] Network error. Falling back to mock data because APP_DEBUG=true: " . $e->getMessage());
                return $this->handleMockRequest($path, $query);
            }
            throw new ApiException('Network error: ' . $e->getMessage(), 503);
        }
    }

    /**
     * Handle mock responses for local testing when SERIES_API_KEY is not set or set to 'mock'.
     */
    private function handleMockRequest(string $path, array $query): array
    {
        $pathLower = strtolower($path);
        
        // 1. Me endpoint
        if (str_contains($pathLower, '/api/me') && !str_contains($pathLower, '/access')) {
            return [
                'id' => 9999,
                'name' => 'Mock Developer',
                'email' => 'mock@seriesjeen.online',
                'is_active' => true,
                'usage_today' => 0
            ];
        }
        
        // 2. Access/Platforms endpoint
        if (str_contains($pathLower, '/api/me/access')) {
            return [
                'platforms' => [
                    [
                        'platform_id' => 1,
                        'name' => 'DramaBox',
                        'image' => '/public/assets/mock_cover.png',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'days_remaining' => 30,
                    ],
                    [
                        'platform_id' => 2,
                        'name' => 'ShortMax',
                        'image' => '/public/assets/mock_cover.png',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'days_remaining' => 30,
                    ],
                    [
                        'platform_id' => 3,
                        'name' => 'ReelShort',
                        'image' => '/public/assets/mock_cover.png',
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                        'days_remaining' => 30,
                    ]
                ]
            ];
        }
        
        // 3. Genres endpoint
        if (str_contains($pathLower, '/genres')) {
            return [
                'genres' => [
                    ['id' => 1, 'name' => 'โรแมนติก (Romantic)'],
                    ['id' => 2, 'name' => 'แฟนตาซี (Fantasy)'],
                    ['id' => 3, 'name' => 'แอคชั่น (Action)'],
                    ['id' => 4, 'name' => 'ดราม่าเข้มข้น (Drama)']
                ]
            ];
        }
        
        // 4. Series List & Search
        if (str_contains($pathLower, '/list') || str_contains($pathLower, '/search') || str_contains($pathLower, '/genre/')) {
            $items = [];
            $titles = [
                'ทวงแค้นรักประธานหมื่นล้าน (CEO Reborn Revenge)',
                'ลิขิตรักเหนือแรงดึงดูด (Gravitational Love)',
                'คืนนี้นีออนต้องเรืองแสง (Neon City Secrets)',
                'บัลลังก์รักในรอยทราย (Desert Throne)',
                'วิวาห์ฟ้าแลบกับเจ้าพ่อมาเฟีย (Mafia Quick Marriage)',
                'ย้อนรอยอดีตรักหมดใจ (Time Travel Back to You)'
            ];
            foreach ($titles as $index => $title) {
                $items[] = [
                    'id' => 'mock_series_' . ($index + 1),
                    'series_id' => 'mock_series_' . ($index + 1),
                    'title' => $title,
                    'cover' => '/public/assets/mock_cover.png',
                    'episode_count' => 10,
                    'genre' => 'โรแมนติก',
                    'description' => 'เรื่องราวความรักและความแค้นในเมืองใหญ่สุดตระการตา...'
                ];
            }
            return [
                'platform_id' => 1,
                'page' => 1,
                'page_size' => 6,
                'total' => 6,
                'items' => $items
            ];
        }
        
        // 5. DRM/Melolo key retrieval
        if (str_contains($pathLower, '/melolo/key')) {
            return [
                'key' => '0123456789abcdef0123456789abcdef'
            ];
        }
        
        // 6. Episode List / Chapters
        if (
            str_contains($pathLower, '/allepisode') ||
            str_contains($pathLower, '/alleps') ||
            str_contains($pathLower, '/episodes') ||
            str_contains($pathLower, '/episode') ||
            str_contains($pathLower, '/chapters') ||
            str_contains($pathLower, '/videos')
        ) {
            $eps = [];
            for ($i = 1; $i <= 10; $i++) {
                $eps[] = [
                    'episode' => $i,
                    'chapterIndex' => $i,
                    'chapterId' => 'mock_ep_' . $i,
                    'id' => 'mock_ep_' . $i,
                    'chapterName' => 'ตอนที่ ' . $i . ' - บทเริ่มความเร้าใจ',
                    'isCharge' => 0,
                    'videoUrl' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
                    'locked' => false,
                    'sources' => [
                        [
                            'quality' => 'auto',
                            'codec' => 'h264',
                            'url' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8'
                        ]
                    ]
                ];
            }
            return $eps;
        }
        
        // 7. Detail endpoint
        if (str_contains($pathLower, '/detail') || isset($query['bookId'])) {
            $seriesId = $query['bookId'] ?? '';
            if ($seriesId === '') {
                $parts = explode('/detail/', $path);
                if (count($parts) > 1) {
                    $seriesId = rawurldecode($parts[1]);
                }
            }
            
            $titles = [
                'mock_series_1' => 'ทวงแค้นรักประธานหมื่นล้าน (CEO Reborn Revenge)',
                'mock_series_2' => 'ลิขิตรักเหนือแรงดึงดูด (Gravitational Love)',
                'mock_series_3' => 'คืนนี้นีออนต้องเรืองแสง (Neon City Secrets)',
                'mock_series_4' => 'บัลลังก์รักในรอยทราย (Desert Throne)',
                'mock_series_5' => 'วิวาห์ฟ้าแลบกับเจ้าพ่อมาเฟีย (Mafia Quick Marriage)',
                'mock_series_6' => 'ย้อนรอยอดีตรักหมดใจ (Time Travel Back to You)'
            ];
            $title = $titles[$seriesId] ?? 'ซีรีส์จำลองเรื่องเด็ด (Mock Epic Series)';
            
            return [
                'id' => $seriesId,
                'bookName' => $title,
                'title' => $title,
                'introduction' => 'เรื่องราวฉบับย่อเกี่ยวกับบทสรุปความบันเทิงระดับห้าดาว ความรัก ความแค้น ความตื่นเต้น และความลับที่จะสั่นคลอนทุกหัวใจ!',
                'desc' => 'เรื่องราวฉบับย่อเกี่ยวกับบทสรุปความบันเทิงระดับห้าดาว ความรัก ความแค้น ความตื่นเต้น และความลับที่จะสั่นคลอนทุกหัวใจ!',
                'coverWap' => '/public/assets/mock_cover.png',
                'cover' => '/public/assets/mock_cover.png',
                'episode_count' => 10,
                'total_episodes' => 10,
                'tags' => ['โรแมนติก', 'ดราม่าเข้มข้น']
            ];
        }
        
        // 8. Play / Stream endpoint
        if (str_contains($pathLower, '/play') || str_contains($pathLower, '/stream')) {
            $episode = 1;
            $parts = explode('/', $path);
            if (count($parts) > 0) {
                $last = end($parts);
                if (is_numeric($last)) {
                    $episode = (int)$last;
                }
            }
            return [
                'episode' => $episode,
                'locked' => false,
                'sources' => [
                    [
                        'quality' => 'auto',
                        'codec' => 'h264',
                        'url' => 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8'
                    ]
                ],
                'subtitles' => []
            ];
        }
        
        return [];
    }
}
