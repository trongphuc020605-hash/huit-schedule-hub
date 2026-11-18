<?php

declare(strict_types=1);

namespace HuitSchedule\Domain;

final class ExpertiseMatcher
{
    /** @var string[] */
    private array $stopwords;

    /** @var array<string, array{label: string, keywords: string[]}> */
    private array $clusters;

    /**
     * @param string[]|null $stopwords
     * @param array<string, array{label: string, keywords: string[]}>|null $clusters
     */
    public function __construct(?array $stopwords = null, ?array $clusters = null)
    {
        $config = function_exists('app_config') ? app_config() : [];
        if ($config === [] && is_file(dirname(__DIR__, 2) . '/config/app.php')) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
        }

        $this->stopwords = $stopwords ?? ($config['academic_stopwords'] ?? []);
        $this->clusters  = $clusters ?? ($config['expertise_clusters'] ?? []);
    }

    public static function removeVietnameseAccents(string $text): string
    {
        $map = [
            'a' => ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ'],
            'e' => ['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ'],
            'i' => ['ì','í','ị','ỉ','ĩ'],
            'o' => ['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ'],
            'u' => ['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ'],
            'y' => ['ỳ','ý','ỵ','ỷ','ỹ'],
            'd' => ['đ'],
            'A' => ['À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ'],
            'E' => ['È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ'],
            'I' => ['Ì','Í','Ị','Ỉ','Ĩ'],
            'O' => ['Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ'],
            'U' => ['Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ'],
            'Y' => ['Ỳ','Ý','Ỵ','Ỷ','Ỹ'],
            'D' => ['Đ'],
        ];

        foreach ($map as $ascii => $chars) {
            $text = str_replace($chars, $ascii, $text);
        }

        return $text;
    }

    public function normalize(string $text): string
    {
        $clean = self::removeVietnameseAccents(trim($text));
        $clean = strtolower($clean);
        // Preserve #, +, . inside tech tokens like c#, c++, .net
        $clean = preg_replace('/[^a-z0-9#+.\/\- ]+/', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        return trim($clean);
    }

    /**
     * Extract meaningful tokens excluding Vietnamese academic stop-words.
     *
     * @return string[]
     */
    public function tokenize(string $text): array
    {
        $normalized = $this->normalize($text);
        if ($normalized === '') {
            return [];
        }
        $parts = preg_split('/[\s,;|\/]+/', $normalized) ?: [];
        $stopSet = array_flip($this->stopwords);
        $tokens = [];

        foreach ($parts as $part) {
            $t = trim($part, '.-');
            if ($t === '' || strlen($t) < 2) {
                continue;
            }
            if (isset($stopSet[$t])) {
                continue;
            }
            $tokens[$t] = $t;
        }

        return array_values($tokens);
    }

    /**
     * Split expertise string into normalized phrases (comma/semicolon separated).
     *
     * @return string[]
     */
    public function extractPhrases(string $text): array
    {
        $parts = preg_split('/[,;|]+/', $text) ?: [];
        $phrases = [];
        foreach ($parts as $part) {
            $norm = $this->normalize($part);
            if ($norm !== '' && strlen($norm) >= 2) {
                $phrases[] = $norm;
            }
        }
        return array_values(array_unique($phrases));
    }

    /**
     * Detect IT clusters matching a text.
     *
     * @return array<string, string[]> clusterKey => matchedKeywords
     */
    public function detectClusters(string $text): array
    {
        $norm = ' ' . $this->normalize($text) . ' ';
        if (trim($norm) === '') {
            return [];
        }

        $detected = [];
        foreach ($this->clusters as $clusterKey => $cluster) {
            $hits = [];
            foreach ($cluster['keywords'] as $kw) {
                $kwNorm = $this->normalize($kw);
                if ($kwNorm === '') {
                    continue;
                }
                if ($this->containsKeyword($norm, $kwNorm)) {
                    $hits[] = $kwNorm;
                }
            }
            if ($hits !== []) {
                $detected[$clusterKey] = array_values(array_unique($hits));
            }
        }

        return $detected;
    }

    /**
     * @param string[] $a
     * @param string[] $b
     */
    public function jaccardSimilarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }
        $setA = array_unique($a);
        $setB = array_unique($b);
        $inter = array_intersect($setA, $setB);
        $union = array_unique(array_merge($setA, $setB));
        return count($union) > 0 ? round(count($inter) / count($union), 4) : 0.0;
    }

    /**
     * Compute Explainable Expertise Match score in [0, 40].
     *
     * @return array{
     *     score: int,
     *     level: string,
     *     matched_keywords: string[],
     *     matched_clusters: string[],
     *     jaccard: float,
     *     reason: string
     * }
     */
    public function match(string $subjectName, string $teacherExpertise): array
    {
        $subjectNorm   = $this->normalize($subjectName);
        $expertiseNorm = $this->normalize($teacherExpertise);

        if ($subjectNorm === '' || $expertiseNorm === '') {
            return [
                'score'            => 0,
                'level'            => 'none',
                'matched_keywords' => [],
                'matched_clusters' => [],
                'jaccard'          => 0.0,
                'reason'           => 'Chưa nhập tên môn học hoặc chuyên môn',
            ];
        }

        $subjectTokens   = $this->tokenize($subjectName);
        $expertiseTokens = $this->tokenize($teacherExpertise);
        $jaccard         = $this->jaccardSimilarity($subjectTokens, $expertiseTokens);

        $subjectClusters   = $this->detectClusters($subjectName);
        $expertiseClusters = $this->detectClusters($teacherExpertise);

        // 1. Check direct phrase or direct domain-keyword hit
        $directHits = [];
        $paddedSubject   = ' ' . $subjectNorm . ' ';
        $paddedExpertise = ' ' . $expertiseNorm . ' ';

        foreach ($this->extractPhrases($teacherExpertise) as $phrase) {
            if (strlen($phrase) >= 3 && $this->containsKeyword($paddedSubject, $phrase)) {
                $directHits[] = $phrase;
            }
        }

        // Check shared taxonomy keywords directly appearing in both subject and expertise
        foreach ($this->clusters as $cluster) {
            foreach ($cluster['keywords'] as $kw) {
                $kwNorm = $this->normalize($kw);
                if ($kwNorm !== '' && $this->containsKeyword($paddedSubject, $kwNorm) && $this->containsKeyword($paddedExpertise, $kwNorm)) {
                    $directHits[] = $kwNorm;
                }
            }
        }

        // Also check significant non-stopword token intersection (>= 2 shared tokens or 1 strong tech token >= 3 chars)
        $sharedTokens = array_values(array_intersect($subjectTokens, $expertiseTokens));
        if (count($sharedTokens) >= 2) {
            $directHits = array_merge($directHits, $sharedTokens);
        }

        $directHits = array_values(array_unique($directHits));

        // Shared IT clusters
        $sharedClusterKeys = array_values(array_intersect(array_keys($subjectClusters), array_keys($expertiseClusters)));
        $sharedClusterLabels = array_map(
            fn(string $k): string => $this->clusters[$k]['label'] ?? $k,
            $sharedClusterKeys
        );

        if ($directHits !== []) {
            $preview = implode(', ', array_slice($directHits, 0, 3));
            return [
                'score'            => 40,
                'level'            => 'direct',
                'matched_keywords' => $directHits,
                'matched_clusters' => $sharedClusterLabels,
                'jaccard'          => $jaccard,
                'reason'           => 'Khớp trực tiếp chuyên môn (' . $preview . ')',
            ];
        }

        if ($sharedClusterKeys !== []) {
            $preview = implode(', ', array_slice($sharedClusterLabels, 0, 2));
            return [
                'score'            => 25,
                'level'            => 'cluster',
                'matched_keywords' => $sharedTokens,
                'matched_clusters' => $sharedClusterLabels,
                'jaccard'          => $jaccard,
                'reason'           => 'Cùng cụm chuyên ngành: ' . $preview,
            ];
        }

        if ($sharedTokens !== [] || $jaccard >= 0.15) {
            $preview = implode(', ', array_slice($sharedTokens, 0, 3));
            return [
                'score'            => 12,
                'level'            => 'partial',
                'matched_keywords' => $sharedTokens,
                'matched_clusters' => [],
                'jaccard'          => $jaccard,
                'reason'           => 'Khớp một phần từ khóa (' . ($preview !== '' ? $preview : 'tương đồng ngữ nghĩa') . ')',
            ];
        }

        return [
            'score'            => 0,
            'level'            => 'none',
            'matched_keywords' => [],
            'matched_clusters' => [],
            'jaccard'          => $jaccard,
            'reason'           => 'Chưa khớp chuyên môn môn học',
        ];
    }

    private function containsKeyword(string $paddedHaystack, string $needle): bool
    {
        $escaped = preg_quote($needle, '/');
        return (bool) preg_match('/(?<![a-z0-9])' . $escaped . '(?![a-z0-9])/i', $paddedHaystack);
    }
}
