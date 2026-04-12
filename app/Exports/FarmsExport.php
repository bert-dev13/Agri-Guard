<?php

namespace App\Exports;

use App\Models\User;
use App\Services\CropTimelineService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FarmsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $query
    ) {}

    /**
     * @return Builder<User>
     */
    public function query(): Builder
    {
        return $this->query->clone();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Farmer Name',
            'Barangay',
            'Crop Type',
            'Farming Stage',
            'Planting Date',
            'Farm Size (ha)',
        ];
    }

    /**
     * @param  User  $user
     * @return array<int, mixed>
     */
    public function map($user): array
    {
        return [
            $user->name,
            $user->farm_barangay_name ?: '—',
            $user->crop_type ?: '—',
            $user->farming_stage ? app(CropTimelineService::class)->displayLabel($user->farming_stage) : '—',
            $user->planting_date?->format('Y-m-d') ?: '—',
            $user->farm_area !== null ? number_format((float) $user->farm_area, 2) : '—',
        ];
    }
}
