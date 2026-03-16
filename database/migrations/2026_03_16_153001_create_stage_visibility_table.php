<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
    {
        Schema::create('stage_visibility', function (Blueprint $table) {
            $table->id();
            $table->string('role_name'); // super_admin, admin, manager, team_lead, sales, marketing
            $table->json('visible_stages'); // [1,2,3,4,5,6,7,8]
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stage_visibility');
    }
};
