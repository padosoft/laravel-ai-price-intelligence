<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Support\Math;

final class Vector
{
    /**
     * Cosine similarity in [0,1] for non-negative vectors (clamped).
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = max(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $va = $a[$i] ?? 0.0;
            $vb = $b[$i] ?? 0.0;
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $cos = $dot / (sqrt($normA) * sqrt($normB));

        return max(0.0, min(1.0, $cos));
    }
}
