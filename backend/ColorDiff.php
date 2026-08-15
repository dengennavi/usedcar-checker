<?php
declare(strict_types=1);

/**
 * ④パネル色差判定（再塗装検出）のコアロジック。
 * GD画像リソースからの矩形領域平均RGB抽出、sRGB→Lab変換、CIEDE2000(ΔE2000)計算。
 */
final class ColorDiff
{
    /**
     * 矩形領域内の平均RGBを算出する。
     * 領域が大きい場合はストライドサンプリングして計算量を抑える。
     *
     * @param resource|\GdImage $im
     * @return array{0:float,1:float,2:float} [r, g, b] (0-255)
     */
    public static function averageRgbOfRegion($im, int $x, int $y, int $w, int $h, int $maxSamples = 10000): array
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

        $rSum = 0;
        $gSum = 0;
        $bSum = 0;
        $n = 0;
        for ($py = 0; $py < $h; $py += $stride) {
            for ($px = 0; $px < $w; $px += $stride) {
                $rgb = imagecolorat($im, $x + $px, $y + $py);
                $rSum += ($rgb >> 16) & 0xFF;
                $gSum += ($rgb >> 8) & 0xFF;
                $bSum += $rgb & 0xFF;
                $n++;
            }
        }

        if ($n === 0) {
            throw new RuntimeException('サンプリング点が0件です');
        }

        return [$rSum / $n, $gSum / $n, $bSum / $n];
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
     * CIEDE2000色差(ΔE00)を計算する (Sharma, Wu, Dalal 2005)。
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
