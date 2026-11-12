<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier 2 splits content work in two: "cms.manage" writes drafts, the new
 * "cms.publish" makes them live. Everyone who could manage content before
 * could also publish, so they get the new permission and lose nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore(['name' => 'cms.publish', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);

        $publish = DB::table('permissions')->where(['name' => 'cms.publish', 'guard_name' => 'web'])->value('id');
        $manage = DB::table('permissions')->where(['name' => 'cms.manage', 'guard_name' => 'web'])->value('id');

        if ($manage !== null) {
            DB::table('model_has_permissions')->where('permission_id', $manage)->get()->each(
                fn ($row) => DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $publish, 'model_type' => $row->model_type, 'model_id' => $row->model_id,
                ])
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $publish = DB::table('permissions')->where(['name' => 'cms.publish', 'guard_name' => 'web'])->value('id');

        DB::table('model_has_permissions')->where('permission_id', $publish)->delete();
        DB::table('permissions')->where('id', $publish)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
