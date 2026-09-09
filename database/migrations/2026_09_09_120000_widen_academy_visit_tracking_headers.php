<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give the visit-tracking columns room for a real request header.
 *
 * These were string() -- varchar(255) -- and hold values the client chooses:
 * two request headers and two body fields. Facebook's in-app browser sends a
 * user agent of nearly three hundred characters, so every visitor arriving from
 * a Facebook link was answered with a 500 and never counted. It was the only
 * error this API logged: ninety-three of them in two days.
 *
 * The controller now truncates as well, which is the part that actually closes
 * it -- a wider column moves the ceiling rather than removing it, and nothing
 * stops a client sending a header of any length. This migration is what stops
 * legitimate values being lost to truncation on the way in: 255 is simply too
 * small for a user agent, and the same codebase already uses text() and
 * string(512) for this field elsewhere.
 */
return new class extends Migration
{
    /** Keep in step with AnalyticsWidgetsController::bounded(). */
    private const WIDTH = 1024;

    public function up(): void
    {
        if (Schema::hasTable('academy_visitors')) {
            Schema::table('academy_visitors', function (Blueprint $table) {
                $table->string('user_agent', self::WIDTH)->nullable()->change();
                $table->string('accept_language', self::WIDTH)->nullable()->change();
            });
        }

        if (Schema::hasTable('academy_visit_events')) {
            Schema::table('academy_visit_events', function (Blueprint $table) {
                $table->string('user_agent', self::WIDTH)->nullable()->change();
                $table->string('path', self::WIDTH)->nullable()->change();
                $table->string('referrer', self::WIDTH)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Deliberately not narrowed back. Rows written since this ran may be
        // longer than 255, and MySQL would truncate them silently on the way
        // down -- losing data to undo a change that lost none.
    }
};
