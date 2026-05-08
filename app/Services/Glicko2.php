<?php

namespace App\Services;

/**
 * Glicko-2 rating system. Implementación según el paper de Mark Glickman:
 * http://www.glicko.net/glicko/glicko2.pdf
 *
 * Cada user tiene 3 valores: rating, rating_deviation (RD) y volatility.
 * Después de cada match calculamos los nuevos para ambos jugadores.
 *
 * Para MVP, cada match es su propio "rating period". El paper formal
 * recomienda agrupar matches en períodos (ej. semanales) para mayor
 * precisión, pero per-match es estándar en plataformas online (Lichess
 * lo hace per-game también).
 */
class Glicko2
{
    private const TAU            = 0.5;        // System constant: limita el cambio de volatility
    private const EPSILON        = 1e-6;       // Tolerancia de convergencia
    private const SCALE          = 173.7178;   // Factor de conversión Glicko ↔ Glicko-2
    private const RATING_OFFSET  = 1500.0;     // Origen del rating
    private const MAX_ITERATIONS = 100;

    /**
     * Calcula los nuevos ratings de host y opponent después de una match.
     *
     * @return array{host: array{rating: float, rd: float, volatility: float}, opponent: array{rating: float, rd: float, volatility: float}}
     */
    public static function update(
        float $hostRating, float $hostRd, float $hostVol,
        float $oppRating,  float $oppRd,  float $oppVol,
        bool $hostWon
    ): array {
        $hostNew = self::computeNew($hostRating, $hostRd, $hostVol, $oppRating, $oppRd, $hostWon ? 1.0 : 0.0);
        $oppNew  = self::computeNew($oppRating,  $oppRd,  $oppVol,  $hostRating, $hostRd, $hostWon ? 0.0 : 1.0);
        return ['host' => $hostNew, 'opponent' => $oppNew];
    }

    /**
     * Calcula el nuevo rating de UN jugador contra un solo oponente, con un score
     * (1 = ganó, 0 = perdió, 0.5 = empate).
     *
     * @return array{rating: float, rd: float, volatility: float}
     */
    private static function computeNew(
        float $rating, float $rd, float $vol,
        float $oppRating, float $oppRd,
        float $score
    ): array {
        // Step 2: convertir a escala Glicko-2
        $mu     = ($rating - self::RATING_OFFSET) / self::SCALE;
        $phi    = $rd / self::SCALE;
        $sigma  = $vol;

        $oppMu  = ($oppRating - self::RATING_OFFSET) / self::SCALE;
        $oppPhi = $oppRd / self::SCALE;

        // Step 3: g(phi) y E (probabilidad esperada de victoria del jugador)
        $g = 1.0 / sqrt(1.0 + 3.0 * $oppPhi * $oppPhi / (M_PI * M_PI));
        $E = 1.0 / (1.0 + exp(-$g * ($mu - $oppMu)));

        // Step 4: varianza estimada v
        $v = 1.0 / ($g * $g * $E * (1.0 - $E));

        // Step 5: delta (estimador de cambio en mu)
        $delta = $v * $g * ($score - $E);

        // Step 6: nueva volatility (algoritmo iterativo)
        $sigmaNew = self::computeNewVolatility($phi, $v, $delta, $sigma);

        // Step 7: actualizar phi (RD)
        $phiStar = sqrt($phi * $phi + $sigmaNew * $sigmaNew);
        $phiNew  = 1.0 / sqrt(1.0 / ($phiStar * $phiStar) + 1.0 / $v);

        // Step 8: actualizar mu (rating)
        $muNew = $mu + $phiNew * $phiNew * $g * ($score - $E);

        // Step 9: convertir de vuelta a escala original
        return [
            'rating'     => self::SCALE * $muNew + self::RATING_OFFSET,
            'rd'         => self::SCALE * $phiNew,
            'volatility' => $sigmaNew,
        ];
    }

    /**
     * Algoritmo iterativo del Step 6 del paper. Encuentra la nueva volatility
     * resolviendo f(x) = 0 vía Illinois algorithm.
     */
    private static function computeNewVolatility(float $phi, float $v, float $delta, float $sigma): float
    {
        $a = log($sigma * $sigma);

        $f = function (float $x) use ($a, $delta, $phi, $v): float {
            $ex = exp($x);
            $num = $ex * ($delta * $delta - $phi * $phi - $v - $ex);
            $den = 2.0 * pow($phi * $phi + $v + $ex, 2);
            return $num / $den - ($x - $a) / (self::TAU * self::TAU);
        };

        // Inicializar A y B
        if ($delta * $delta > $phi * $phi + $v) {
            $B = log($delta * $delta - $phi * $phi - $v);
        } else {
            $k = 1;
            while ($f($a - $k * self::TAU) < 0 && $k < self::MAX_ITERATIONS) {
                $k++;
            }
            $B = $a - $k * self::TAU;
        }

        $A  = $a;
        $fA = $f($A);
        $fB = $f($B);

        // Illinois algorithm
        $iter = 0;
        while (abs($B - $A) > self::EPSILON && $iter < self::MAX_ITERATIONS) {
            $C  = $A + ($A - $B) * $fA / ($fB - $fA);
            $fC = $f($C);
            if ($fC * $fB < 0) {
                $A  = $B;
                $fA = $fB;
            } else {
                $fA = $fA / 2.0;
            }
            $B  = $C;
            $fB = $fC;
            $iter++;
        }

        return exp($A / 2.0);
    }
}
