<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = [
            ['code' => 'ADMIN', 'description' => 'Administrador'],
            ['code' => 'SUPORTE', 'description' => 'Suporte'],
            ['code' => 'USUARIOS', 'description' => 'Usuarios'],
        ];

        foreach ($groups as $group) {
            $exists = DB::table('groups')->where('code', $group['code'])->exists();

            if ($exists) {
                DB::table('groups')->where('code', $group['code'])->update([
                    'description' => $group['description'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('groups')->insert([
                    'code' => $group['code'],
                    'description' => $group['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('groups')->whereIn('code', ['SUPORTE', 'USUARIOS'])->delete();

        DB::table('groups')->where('code', 'ADMIN')->update([
            'description' => 'Administrador',
            'updated_at' => now(),
        ]);
    }
};
