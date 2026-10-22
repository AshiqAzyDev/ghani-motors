<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE bookings DROP INDEX bookings_one_active_test_drive');
        DB::statement('ALTER TABLE bookings DROP COLUMN active_test_drive_key');
        DB::statement("ALTER TABLE bookings MODIFY type ENUM('reservation', 'test_drive', 'viewing') NOT NULL");
        DB::statement("ALTER TABLE bookings ADD COLUMN active_test_drive_key VARCHAR(80) AS (IF(type IN ('test_drive', 'viewing') AND status NOT IN ('cancelled', 'completed'), CONCAT(vehicle_id, '|', preferred_on, '|', IFNULL(TIME_FORMAT(preferred_time, '%H:%i'), '')), NULL)) STORED");
        DB::statement('ALTER TABLE bookings ADD UNIQUE KEY bookings_one_active_test_drive (active_test_drive_key)');
    }

    public function down(): void
    {
        DB::statement("UPDATE bookings SET type = 'test_drive' WHERE type = 'viewing'");
        DB::statement('ALTER TABLE bookings DROP INDEX bookings_one_active_test_drive');
        DB::statement('ALTER TABLE bookings DROP COLUMN active_test_drive_key');
        DB::statement("ALTER TABLE bookings MODIFY type ENUM('reservation', 'test_drive') NOT NULL");
        DB::statement("ALTER TABLE bookings ADD COLUMN active_test_drive_key VARCHAR(80) AS (IF(type = 'test_drive' AND status NOT IN ('cancelled', 'completed'), CONCAT(vehicle_id, '|', preferred_on, '|', IFNULL(TIME_FORMAT(preferred_time, '%H:%i'), '')), NULL)) STORED");
        DB::statement('ALTER TABLE bookings ADD UNIQUE KEY bookings_one_active_test_drive (active_test_drive_key)');
    }
};
