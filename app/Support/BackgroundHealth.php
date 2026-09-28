<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Is anyone doing the background work? The scheduler and the queue worker are
 * separate containers; when one stops, nothing errors — mails just never
 * leave. Both leave a heartbeat in the (shared, database) cache: the scheduler
 * every minute, the worker whenever it runs the QueueHeartbeat job the
 * scheduler hands it every five minutes. The admin shows a warning when a
 * heartbeat is overdue.
 */
final class BackgroundHealth
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    /** Minutes of silence before we worry. */
    private const LIMITS = [self::SCHEDULER => 5, self::QUEUE => 15];

    public static function beat(string $which): void
    {
        Cache::forever("health.{$which}", now()->getTimestamp());
    }

    public static function lastBeat(string $which): ?CarbonImmutable
    {
        $timestamp = Cache::get("health.{$which}");

        return $timestamp ? CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone')) : null;
    }

    public static function enabled(): bool
    {
        return (bool) config('queue.health_checks');
    }

    /** @return list<string> what staff should hear about, in plain Dutch */
    public static function problems(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $problems = [];
        $schedulerDown = self::overdue(self::SCHEDULER);

        if ($schedulerDown) {
            $problems[] = 'De planner (scheduler-container) draait niet'.self::since(self::SCHEDULER).'. '
                .'Zonder planner worden oude gegevens niet opgeruimd en kunnen we de wachtrij niet controleren.';
        }

        // Without the scheduler there is no heartbeat job to go by; mails stuck in line tell the same story.
        $queueDown = $schedulerDown ? self::waitingSince(self::LIMITS[self::QUEUE]) > 0 : self::overdue(self::QUEUE);

        if ($queueDown) {
            $waiting = self::waitingSince(0);
            $problems[] = 'De wachtrij (queue-container) verwerkt niets'.($schedulerDown ? '' : self::since(self::QUEUE)).'. '
                .($waiting > 0
                    ? "Er wachten {$waiting} e-mail(s) op verzending; ze vertrekken vanzelf zodra de wachtrij weer draait."
                    : 'E-mails worden pas verstuurd zodra de wachtrij weer draait.');
        }

        return $problems;
    }

    private static function overdue(string $which): bool
    {
        $last = self::lastBeat($which);

        return $last === null || $last->lt(now()->subMinutes(self::LIMITS[$which]));
    }

    private static function since(string $which): string
    {
        $last = self::lastBeat($which);

        return $last ? ' sinds '.$last->locale('nl')->translatedFormat('D j M H:i') : '';
    }

    /** Jobs ready to run for at least this many minutes that no worker has picked up. */
    private static function waitingSince(int $minutes): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        return DB::table(config('queue.connections.database.table', 'jobs'))
            ->whereNull('reserved_at')
            ->where('payload', 'not like', '%QueueHeartbeat%')
            ->where('available_at', '<=', now()->subMinutes($minutes)->getTimestamp())
            ->count();
    }
}
