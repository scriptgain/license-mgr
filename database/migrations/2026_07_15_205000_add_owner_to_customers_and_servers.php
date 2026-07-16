<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Owner of this customer (and, by inheritance, their licenses).
            // Null = unassigned / admin-only. Non-admins see only their own.
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('license_servers', function (Blueprint $table) {
            // Owner of this node. Null = unassigned / admin-only.
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
        Schema::table('license_servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
