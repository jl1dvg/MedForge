<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Support;

use Carbon\Carbon;

/**
 * Calcula segundos transcurridos dentro del horario laboral configurado.
 * Descuenta horas fuera de turno, días inhabilitados y festivos.
 */
class BusinessHoursCalculator
{
    /** @var array<string, array{enabled: bool, start: string, end: string}> */
    private array $schedule;
    private string $timezone;
    /** @var array<int, string> Fechas YYYY-MM-DD */
    private array $holidays;

    /**
     * @param array<string, mixed> $schedule  JSON decodificado de whatsapp_handoff_business_schedule
     * @param string               $timezone  Ej: "America/Guayaquil"
     * @param array<int, string>   $holidays  Fechas YYYY-MM-DD de whatsapp_handoff_business_holidays
     */
    public function __construct(array $schedule, string $timezone, array $holidays)
    {
        $this->schedule = $schedule;
        $this->timezone = $timezone;
        $this->holidays = array_values(array_filter($holidays));
    }

    /**
     * Segundos laborales entre dos timestamps.
     * Retorna 0 si $end <= $start o no hay horario configurado.
     */
    public function businessSecondsElapsed(Carbon $start, Carbon $end): int
    {
        if ($end->lessThanOrEqualTo($start) || empty($this->schedule)) {
            return 0;
        }

        $localStart = $start->copy()->setTimezone($this->timezone);
        $localEnd   = $end->copy()->setTimezone($this->timezone);

        $total    = 0;
        $current  = $localStart->copy()->startOfDay();
        $endDay   = $localEnd->copy()->startOfDay();

        while ($current->lessThanOrEqualTo($endDay)) {
            $dateStr = $current->format('Y-m-d');
            $dayName = strtolower($current->format('l')); // monday, tuesday...

            $dayConfig = $this->schedule[$dayName] ?? null;

            if ($dayConfig !== null
                && ($dayConfig['enabled'] ?? false)
                && !in_array($dateStr, $this->holidays, true)
            ) {
                [$sh, $sm] = explode(':', $dayConfig['start']);
                [$eh, $em] = explode(':', $dayConfig['end']);

                $workStart = $current->copy()->setTime((int) $sh, (int) $sm, 0);
                $workEnd   = $current->copy()->setTime((int) $eh, (int) $em, 0);

                $overlapStart = max($localStart->timestamp, $workStart->timestamp);
                $overlapEnd   = min($localEnd->timestamp,   $workEnd->timestamp);

                if ($overlapEnd > $overlapStart) {
                    $total += $overlapEnd - $overlapStart;
                }
            }

            $current->addDay();
        }

        return $total;
    }

    public function toMinutes(int $seconds, int $decimals = 1): float
    {
        return round($seconds / 60, $decimals);
    }
}
