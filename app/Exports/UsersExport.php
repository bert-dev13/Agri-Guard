<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping
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
            'ID',
            'Name',
            'Email',
            'Role',
            'Municipality',
            'Barangay',
            'Crop',
            'Farming stage',
            'Planting date',
            'Farm area (ha)',
            'Latitude',
            'Longitude',
            'Field condition',
            'Email verified at',
            'Created at',
        ];
    }

    /**
     * @param  User  $user
     * @return array<int, mixed>
     */
    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->isAdmin() ? 'admin' : 'farmer',
            $user->farm_municipality ?? '',
            $user->farm_barangay_name,
            $user->crop_type ?? '',
            $user->farming_stage ?? '',
            $user->planting_date?->format('Y-m-d') ?? '',
            $user->farm_area ?? '',
            $user->farm_lat ?? '',
            $user->farm_lng ?? '',
            $user->field_condition ?? '',
            $user->email_verified_at?->toIso8601String() ?? '',
            $user->created_at?->toIso8601String() ?? '',
        ];
    }
}
