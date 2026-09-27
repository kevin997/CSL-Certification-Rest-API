<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $credentialColumns = ['api_key', 'api_secret', 'bearer_token', 'username', 'password'];

    public function up(): void
    {
        Schema::table('third_party_services', function (Blueprint $table) {
            $table->text('api_key')->nullable()->change();
            $table->text('api_secret')->nullable()->change();
            $table->text('username')->nullable()->change();
            $table->text('password')->nullable()->change();
        });

        DB::table('third_party_services')
            ->select(array_merge(['id'], $this->credentialColumns))
            ->orderBy('id')
            ->chunkById(100, function ($services): void {
                foreach ($services as $service) {
                    $updates = [];

                    foreach ($this->credentialColumns as $column) {
                        if ($service->{$column} !== null) {
                            $updates[$column] = Crypt::encryptString((string) $service->{$column});
                        }
                    }

                    if ($updates !== []) {
                        DB::table('third_party_services')->where('id', $service->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('third_party_services')
            ->select(array_merge(['id'], $this->credentialColumns))
            ->orderBy('id')
            ->chunkById(100, function ($services): void {
                foreach ($services as $service) {
                    $updates = [];

                    foreach ($this->credentialColumns as $column) {
                        if ($service->{$column} !== null) {
                            $updates[$column] = Crypt::decryptString((string) $service->{$column});
                        }
                    }

                    if ($updates !== []) {
                        DB::table('third_party_services')->where('id', $service->id)->update($updates);
                    }
                }
            });

        Schema::table('third_party_services', function (Blueprint $table) {
            $table->string('api_key')->nullable()->change();
            $table->string('api_secret')->nullable()->change();
            $table->string('username')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }
};
