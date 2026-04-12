<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CropTimelineService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CropTimelineServiceTest extends TestCase
{
    #[Test]
    public function rice_early_phase_uses_planting_date(): void
    {
        $svc = new CropTimelineService;
        $user = new User([
            'crop_type' => 'Rice',
            'planting_date' => Carbon::now()->subDays(3)->toDateString(),
        ]);

        $out = $svc->inferExpectedStageFromPlanting($user, $svc->stageDurationsForCrop('Rice'));

        $this->assertSame('planting', $out['key']);
        $this->assertTrue($out['has_planting_date']);
        $this->assertSame(3, $out['days_since_planting']);
    }

    #[Test]
    public function rice_crosses_into_vegetative_after_cumulative_window(): void
    {
        $svc = new CropTimelineService;
        $user = new User([
            'crop_type' => 'Rice',
            'planting_date' => Carbon::now()->subDays(25)->toDateString(),
        ]);

        $out = $svc->inferExpectedStageFromPlanting($user, $svc->stageDurationsForCrop('Rice'));

        $this->assertSame('vegetative', $out['key']);
        $this->assertSame(25, $out['days_since_planting']);
    }

    #[Test]
    public function future_planting_stays_in_planting_bucket_with_countdown(): void
    {
        $svc = new CropTimelineService;
        $user = new User([
            'crop_type' => 'Rice',
            'planting_date' => Carbon::now()->addDays(5)->toDateString(),
        ]);

        $out = $svc->inferExpectedStageFromPlanting($user);

        $this->assertSame('planting', $out['key']);
        $this->assertSame(5, $out['days_until_planting']);
        $this->assertSame(0, $out['days_since_planting']);
    }

    #[Test]
    public function missing_planting_date_returns_not_has_planting_date(): void
    {
        $svc = new CropTimelineService;
        $user = new User(['crop_type' => 'Rice']);

        $out = $svc->inferExpectedStageFromPlanting($user);

        $this->assertFalse($out['has_planting_date']);
        $this->assertNull($out['days_since_planting']);
    }

    #[Test]
    public function planting_stage_window_starts_on_planting_date_not_before(): void
    {
        $svc = new CropTimelineService;
        $line = $svc->formatStageTypicalWindow('Planting', '2026-04-09', 'Corn');

        $this->assertStringContainsString('Apr 9', $line);
        $this->assertStringContainsString('Apr 16', $line);
        $this->assertStringNotContainsString('Apr 6', $line);
    }

    #[Test]
    public function apply_calendar_status_marks_current_row(): void
    {
        $svc = new CropTimelineService;
        $timeline = [
            ['stage' => 'Planting', 'target_date' => '2026-01-01', 'status' => 'upcoming'],
            ['stage' => 'Early Growth', 'target_date' => '2026-01-08', 'status' => 'upcoming'],
            ['stage' => 'Vegetative', 'target_date' => '2026-02-01', 'status' => 'upcoming'],
            ['stage' => 'Flowering', 'target_date' => '2026-03-01', 'status' => 'upcoming'],
            ['stage' => 'Harvest', 'target_date' => '2026-04-01', 'status' => 'upcoming'],
        ];

        $out = $svc->applyCalendarStatusToTimeline($timeline, 'vegetative');

        $this->assertSame('completed', $out[0]['status']);
        $this->assertSame('completed', $out[1]['status']);
        $this->assertSame('current', $out[2]['status']);
        $this->assertSame('upcoming', $out[3]['status']);
        $this->assertSame('upcoming', $out[4]['status']);
    }
}
