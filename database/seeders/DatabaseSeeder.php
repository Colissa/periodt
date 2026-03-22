<?php

namespace Database\Seeders;

use App\Models\Cycle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $user = User::create([
            'name' => 'Colissa',
            'email' => 'colissa@gmail.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $cycles = [
            ['start_date' => '2023-02-19', 'end_date' => '2023-02-22', 'period_length' => 4],
            ['start_date' => '2023-03-20', 'end_date' => '2023-03-22', 'period_length' => 3],
            ['start_date' => '2023-04-17', 'end_date' => '2023-04-21', 'period_length' => 5],
            ['start_date' => '2023-05-14', 'end_date' => '2023-05-17', 'period_length' => 4],
            ['start_date' => '2023-06-08', 'end_date' => '2023-06-11', 'period_length' => 4],
            ['start_date' => '2023-07-08', 'end_date' => '2023-07-12', 'period_length' => 5],
            ['start_date' => '2023-08-02', 'end_date' => '2023-08-05', 'period_length' => 4],
            ['start_date' => '2023-08-31', 'end_date' => '2023-09-03', 'period_length' => 4],
            ['start_date' => '2023-10-01', 'end_date' => '2023-10-05', 'period_length' => 5],
            ['start_date' => '2023-10-27', 'end_date' => '2023-10-31', 'period_length' => 5],
            ['start_date' => '2023-11-21', 'end_date' => '2023-11-24', 'period_length' => 4],
            ['start_date' => '2023-12-20', 'end_date' => '2023-12-23', 'period_length' => 4],
            ['start_date' => '2024-01-19', 'end_date' => '2024-01-24', 'period_length' => 6],
            ['start_date' => '2024-02-17', 'end_date' => '2024-02-21', 'period_length' => 5],
            ['start_date' => '2024-03-16', 'end_date' => '2024-03-20', 'period_length' => 5],
            ['start_date' => '2024-04-12', 'end_date' => '2024-04-16', 'period_length' => 5],
            ['start_date' => '2024-05-07', 'end_date' => '2024-05-10', 'period_length' => 4],
            ['start_date' => '2024-06-04', 'end_date' => '2024-06-08', 'period_length' => 5],
            ['start_date' => '2024-07-01', 'end_date' => '2024-07-05', 'period_length' => 5],
            ['start_date' => '2024-07-29', 'end_date' => '2024-08-02', 'period_length' => 5],
            ['start_date' => '2024-08-25', 'end_date' => '2024-08-29', 'period_length' => 5],
            ['start_date' => '2024-09-20', 'end_date' => '2024-09-24', 'period_length' => 5],
            ['start_date' => '2024-10-18', 'end_date' => '2024-10-22', 'period_length' => 5],
            ['start_date' => '2024-11-12', 'end_date' => '2024-11-15', 'period_length' => 4],
            ['start_date' => '2024-12-14', 'end_date' => '2024-12-17', 'period_length' => 4],
            ['start_date' => '2025-01-05', 'end_date' => '2025-01-05', 'period_length' => 1],
            ['start_date' => '2025-01-09', 'end_date' => '2025-01-12', 'period_length' => 4],
            ['start_date' => '2025-02-05', 'end_date' => '2025-02-08', 'period_length' => 4],
            ['start_date' => '2025-03-06', 'end_date' => '2025-03-07', 'period_length' => 2],
            ['start_date' => '2025-04-02', 'end_date' => '2025-04-05', 'period_length' => 4],
            ['start_date' => '2025-04-27', 'end_date' => '2025-04-30', 'period_length' => 4],
            ['start_date' => '2025-05-23', 'end_date' => '2025-05-26', 'period_length' => 4],
            ['start_date' => '2025-06-19', 'end_date' => '2025-06-20', 'period_length' => 2],
            ['start_date' => '2025-07-13', 'end_date' => '2025-07-17', 'period_length' => 5],
            ['start_date' => '2025-08-07', 'end_date' => '2025-08-10', 'period_length' => 4],
            ['start_date' => '2025-09-01', 'end_date' => '2025-09-04', 'period_length' => 4],
            ['start_date' => '2025-09-28', 'end_date' => '2025-10-01', 'period_length' => 4],
            ['start_date' => '2025-10-26', 'end_date' => '2025-10-29', 'period_length' => 4],
            ['start_date' => '2025-11-22', 'end_date' => '2025-11-24', 'period_length' => 3],
            ['start_date' => '2025-12-19', 'end_date' => '2025-12-22', 'period_length' => 4],
            ['start_date' => '2026-01-18', 'end_date' => '2026-01-21', 'period_length' => 4],
            ['start_date' => '2026-02-16', 'end_date' => '2026-02-18', 'period_length' => 3],
        ];

        // Insert all cycles
        foreach ($cycles as $data) {
            Cycle::create([
                'user_id' => $user->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'period_length' => $data['period_length'],
            ]);
        }

        // Calculate cycle lengths between consecutive periods
        $allCycles = Cycle::where('user_id', $user->id)
            ->orderBy('start_date')
            ->get();

        for ($i = 0; $i < $allCycles->count() - 1; $i++) {
            $allCycles[$i]->cycle_length = $allCycles[$i]->start_date->diffInDays($allCycles[$i + 1]->start_date);
            $allCycles[$i]->save();
        }
    }
}
