<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->unsignedBigInteger('net_payable')->default(0);
        });

        DB::table('settlements')
            ->where('net_payout', '>', 0)
            ->update(['net_payable' => DB::raw('net_payout')]);
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->dropColumn('net_payable');
        });
    }
};
