<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Zone;
use App\Models\Table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->boolean('is_bar')->default(false)->after('is_active');
        });

        // Auto-create bar tables from floor_layout data
        $zones = Zone::all();
        foreach ($zones as $zone) {
            $layout = $zone->floor_layout;
            if (!$layout || !isset($layout['objects'])) {
                continue;
            }

            foreach ($layout['objects'] as $obj) {
                if (($obj['type'] ?? '') !== 'bar') {
                    continue;
                }

                // Check if bar table already exists for this zone
                $exists = Table::where('zone_id', $zone->id)->where('is_bar', true)->exists();
                if ($exists) {
                    continue;
                }

                // Find the next table number in this zone
                $maxNumber = Table::where('zone_id', $zone->id)->max('number');
                $nextNumber = $maxNumber ? ((int)$maxNumber + 1) : 1;

                Table::create([
                    'restaurant_id' => $zone->restaurant_id,
                    'zone_id' => $zone->id,
                    'number' => (string)$nextNumber,
                    'name' => 'Бар',
                    'seats' => 6,
                    'shape' => 'rectangle',
                    'position_x' => $obj['x'] ?? 0,
                    'position_y' => $obj['y'] ?? 0,
                    'width' => $obj['width'] ?? 200,
                    'height' => $obj['height'] ?? 60,
                    'rotation' => $obj['rotation'] ?? 0,
                    'status' => 'free',
                    'is_active' => true,
                    'is_bar' => true,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete bar tables first
        Table::where('is_bar', true)->delete();

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('is_bar');
        });
    }
};
