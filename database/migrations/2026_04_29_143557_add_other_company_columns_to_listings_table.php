<?php
// database/migrations/2025_01_15_000000_add_other_company_columns_to_listings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOtherCompanyColumnsToListingsTable extends Migration
{
    public function up()
    {
        Schema::table('listings', function (Blueprint $table) {
            // Other company fields for sold
            $table->string('sold_by_company_name')->nullable()->after('sold_by');
            $table->string('sold_by_agent_name')->nullable()->after('sold_by_company_name');
            $table->string('sold_by_agent_phone')->nullable()->after('sold_by_agent_name');
            $table->string('sold_by_agent_email')->nullable()->after('sold_by_agent_phone');
            
            // Other company fields for rented
            $table->string('rented_by_company_name')->nullable()->after('rented_by');
            $table->string('rented_by_agent_name')->nullable()->after('rented_by_company_name');
            $table->string('rented_by_agent_phone')->nullable()->after('rented_by_agent_name');
            $table->string('rented_by_agent_email')->nullable()->after('rented_by_agent_phone');
            
            // Add index for better performance
            $table->index('sold_by_company_name');
            $table->index('rented_by_company_name');
        });
    }

    public function down()
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn([
                'sold_by_company_name',
                'sold_by_agent_name', 
                'sold_by_agent_phone',
                'sold_by_agent_email',
                'rented_by_company_name',
                'rented_by_agent_name',
                'rented_by_agent_phone',
                'rented_by_agent_email'
            ]);
        });
    }
}