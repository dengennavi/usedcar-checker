<?php
declare(strict_types=1);

/**
 * ④パネル色差判定（再塗装検出）のコアロジック。
 * GD画像リソースからの矩形領域サンプリング、sRGB→Lab変換、CIEDE2000(ΔE2000)計算。
 */
final class ColorDiff
{
    /**
     * 矩形領域から中央値RGBと明度(L*)のばらつき(標準偏差)を算出する。
     * 領域が大きい場合はストライドサンプリングして計算量を抑える。
     *
     * 光沢塗装は周囲の空・建物を映り込ませ、矩形内の一部にのみ局所的な
     * ハイライトが出ることがある。平均値だとこの外れ値に引っ張られるため、
     * 外れ値に強い中央値を使う。あわせて明度の標準偏差を返すことで、
     * 呼び出し側が「範囲内に映り込みが混ざっていないか」を判定できるようにする。
     *
     * @param resource|\GdImage $im
     * @return array{rgbMedian: array{0:float,1:float,2:float}, lStdDev: float, sampleCount: int}
     */
    public static function sampleRegion($im, int $x, int $y, int $w, int $h, int $maxSamples = 10000): array
    {
        $imgW = imagesx($im);
        $imgH = imagesy($im);

        $x = max(0, $x);
        $y = max(0, $y);
        $w = min($w, $imgW - $x);
        $h = min($h, $imgH - $y);

        if ($w <= 0 || $h <= 0) {
            throw new InvalidArgumentException('矩形範囲が画像の範囲内にありません');
        }

        $totalPixels = $w * $h;
        $stride = max(1, (int) floor(sqrt($totalPixels / $maxSamples)));

        $rSamples = [];
        $gSamples = [];
        $bSamples = [];
        $lSamples = [];
        for ($py = 0; $py < $h; $py += $stride) {
            for ($px = 0; $px < $w; $px += $stride) {
                $rgb = imagecolorat($im, $x + $px, $y + $py);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $rSamples[] = $r;
                $gSamples[] = $g;
                $bSamples[] = $b;
                [$L, , ] = self::srgbToLab((float) $r, (float) $g, (float) $b);
                $lSamples[] = $L;
            }
        }

        if (empty($rSamples)) {
            throw new RuntimeException('サンプリング点が0件です');
        }

        return [
            'rgbMedian' => [self::median($rSamples), self::median($gSamples), self::median($bSamples)],
            'lStdDev' => self::stdDev($lSamples),
            'sampleCount' => count($rSamples),
        ];
    }

    /**
     * @param array<int,float|int> $values
     */
    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        if ($n % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }
        return (float) $values[$mid];
    }

    /**
     * @param array<int,float> $values
     */
    private static function stdDev(array $values): float
    {
        $n = count($values);
        if ($n <= 1) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(static fn($v) => ($v - $mean) ** 2, $values)) / $n;
        return sqrt($variance);
    }

    /**
     * sRGB(0-255) を CIE Lab (D65白色点) に変換する。
     *
     * @return array{0:float,1:float,2:float} [L, a, b]
     */
    public static function srgbToLab(float $r, float $g, float $b): array
    {
        $r /= 255;
        $g /= 255;
        $b /= 255;

        $linearize = static function (float $c): float {
            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $r = $linearize($r);
        $g = $linearize($g);
        $b = $linearize($b);

        // linear sRGB -> XYZ (D65)
        $x = $r * 0.4124564 + $g * 0.3575761 + $b * 0.1804375;
        $y = $r * 0.2126729 + $g * 0.7151522 + $b * 0.0721750;
        $z = $r * 0.0193339 + $g * 0.1191920 + $b * 0.9503041;

        // D65白色点で正規化
        $xn = 0.95047;
        $yn = 1.0;
        $zn = 1.08883;

        $f = static function (float $t): float {
            $delta = 6 / 29;
            return $t > $delta ** 3 ? $t ** (1 / 3) : ($t / (3 * $delta ** 2) + 4 / 29);
        };

        $fx = $f($x / $xn);
        $fy = $f($y / $yn);
        $fz = $f($z / $zn);

        $L = 116 * $fy - 16;
        $a = 500 * ($fx - $fy);
        $bLab = 200 * ($fy - $fz);

        return [$L, $a, $bLab];
    }

    /**
     * a*b*平面上のユークリッド距離（Δab = sqrt(Δa²+Δb²)）。明度(L)成分は含まない。
     *
     * 実車テストで、湾曲したパネルでは太陽光の当たり方の違いにより
     * 同一塗装でもLだけが大きくズレるケースが確認された（a値は完全一致でもL差だけで
     * ΔE2000が閾値を超え「再塗装の可能性あり」と誤判定した）。
     * 色相・彩度（a,b）は照明条件に比較的左右されにくいため、再塗装検出の主判定には
     * こちらを用いる。
     *
     * 注意: CIE正式のΔC*（クロマ差 = C2*-C1*、符号付きスカラー）とは別物。
     * ここでは a,b平面上の2点間距離を指す（呼び方の混同を避けるため deltaAb と命名）。
     *
     * @param array{0:float,1:float,2:float} $lab1
     * @param array{0:float,1:float,2:float} $lab2
     */
    public static function deltaAb(array $lab1, array $lab2): float
    {
        [, $a1, $b1] = $lab1;
        [, $a2, $b2] = $lab2;

        return sqrt(($a2 - $a1) ** 2 + ($b2 - $b1) ** 2);
    }

    /**
     * CIEDE2000色差(ΔE00)を計算する (Sharma, Wu, Dalal 2005)。
     * 主判定には使わず、参考値として結果に含める（Lの影響を受けるため）。
     *
     * @param array{0:float,1:float,2:float} $lab1
     * @param array{0:float,1:float,2:float} $lab2
     */
    public static function ciede2000(array $lab1, array $lab2, float $kl = 1.0, float $kc = 1.0, float $kh = 1.0): float
    {
        [$L1, $a1, $b1] = $lab1;
        [$L2, $a2, $b2] = $lab2;

        $C1 = sqrt($a1 * $a1 + $b1 * $b1);
        $C2 = sqrt($a2 * $a2 + $b2 * $b2);
        $Cbar = ($C1 + $C2) / 2;

        $G = 0.5 * (1 - sqrt(($Cbar ** 7) / ($Cbar ** 7 + 25 ** 7)));

        $a1p = (1 + $G) * $a1;
        $a2p = (1 + $G) * $a2;

        $C1p = sqrt($a1p * $a1p + $b1 * $b1);
        $C2p = sqrt($a2p * $a2p + $b2 * $b2);

        $h1p = ($a1p === 0.0 && $b1 === 0.0) ? 0.0 : fmod(rad2deg(atan2($b1, $a1p)) + 360, 360);
        $h2p = ($a2p === 0.0 && $b2 === 0.0) ? 0.0 : fmod(rad2deg(atan2($b2, $a2p)) + 360, 360);

        $deltaLp = $L2 - $L1;
        $deltaCp = $C2p - $C1p;

        if ($C1p * $C2p === 0.0) {
            $deltahp = 0.0;
        } else {
            $dh = $h2p - $h1p;
            if (abs($dh) <= 180) {
                $deltahp = $dh;
            } elseif ($dh > 180) {
                $deltahp = $dh - 360;
            } else {
                $deltahp = $dh + 360;
            }
        }
        $deltaHp = 2 * sqrt($C1p * $C2p) * sin(deg2rad($deltahp) / 2);

        $Lbarp = ($L1 + $L2) / 2;
        $Cbarp = ($C1p + $C2p) / 2;

        if ($C1p * $C2p === 0.0) {
            $hbarp = $h1p + $h2p;
        } else {
            $dh2 = abs($h1p - $h2p);
            if ($dh2 <= 180) {
                $hbarp = ($h1p + $h2p) / 2;
            } elseif (($h1p + $h2p) < 360) {
                $hbarp = ($h1p + $h2p + 360) / 2;
            } else {
                $hbarp = ($h1p + $h2p - 360) / 2;
            }
        }

        $T = 1
            - 0.17 * cos(deg2rad($hbarp - 30))
            + 0.24 * cos(deg2rad(2 * $hbarp))
            + 0.32 * cos(deg2rad(3 * $hbarp + 6))
            - 0.20 * cos(deg2rad(4 * $hbarp - 63));

        $deltaTheta = 30 * exp(-(($hbarp - 275) / 25) ** 2);
        $Rc = 2 * sqrt(($Cbarp ** 7) / ($Cbarp ** 7 + 25 ** 7));
        $Sl = 1 + (0.015 * ($Lbarp - 50) ** 2) / sqrt(20 + ($Lbarp - 50) ** 2);
        $Sc = 1 + 0.045 * $Cbarp;
        $Sh = 1 + 0.015 * $Cbarp * $T;
        $Rt = -sin(deg2rad(2 * $deltaTheta)) * $Rc;

        $termL = $deltaLp / ($kl * $Sl);
        $termC = $deltaCp / ($kc * $Sc);
        $termH = $deltaHp / ($kh * $Sh);

        return sqrt($termL ** 2 + $termC ** 2 + $termH ** 2 + $Rt * $termC * $termH);
    }
}
