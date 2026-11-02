<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CURRENT_COORDINATES = [
        'latitude' => '-12.009536180504373',
        'longitude' => '-76.82422937768825',
    ];

    private const SEEDED_COORDINATES = [
        'latitude' => '-12.0125000',
        'longitude' => '-76.8285000',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceCoordinates(self::SEEDED_COORDINATES, self::CURRENT_COORDINATES);
        $this->replaceCoordinates(
            ['latitude' => '-12.0168500', 'longitude' => '-76.8211200'],
            self::CURRENT_COORDINATES
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceCoordinates(self::CURRENT_COORDINATES, self::SEEDED_COORDINATES);
    }

    /**
     * @param  array{latitude: string, longitude: string}  $source
     * @param  array{latitude: string, longitude: string}  $destination
     */
    private function replaceCoordinates(array $source, array $destination): void
    {
        DB::transaction(function () use ($source, $destination): void {
            $settings = DB::table('system_settings')
                ->whereIn('setting_key', ['restaurant_latitude', 'restaurant_longitude'])
                ->pluck('setting_value', 'setting_key');

            if (
                ! isset($settings['restaurant_latitude'], $settings['restaurant_longitude'])
                || (float) $settings['restaurant_latitude'] !== (float) $source['latitude']
                || (float) $settings['restaurant_longitude'] !== (float) $source['longitude']
            ) {
                return;
            }

            DB::table('system_settings')->where('setting_key', 'restaurant_latitude')->update([
                'setting_value' => $destination['latitude'],
                'updated_at' => now(),
            ]);
            DB::table('system_settings')->where('setting_key', 'restaurant_longitude')->update([
                'setting_value' => $destination['longitude'],
                'updated_at' => now(),
            ]);
        });
    }
};
