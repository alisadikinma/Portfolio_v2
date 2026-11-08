<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Adds a public URL slug to awards: nullable column, backfill, then unique index.
     * The backfill mirrors Spatie HasSlug (Str::slug of the title, numeric suffix from 2)
     * so rows created before and after this migration look the same.
     */
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
        });

        $this->backfillSlugs();

        Schema::table('awards', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    /**
     * Fills every award that has no slug yet, in id order (deterministic).
     * Idempotent: rows that already have a slug keep it and reserve it.
     */
    public function backfillSlugs(): void
    {
        $taken = DB::table('awards')->whereNotNull('slug')->pluck('slug')->flip()->all();

        DB::table('awards')->whereNull('slug')->orderBy('id')->select(['id', 'title'])
            ->chunkById(100, function ($awards) use (&$taken) {
                foreach ($awards as $award) {
                    $base = Str::slug((string) $award->title);
                    if ($base === '') {
                        $base = 'award-'.$award->id;
                    }

                    $slug = $base;
                    for ($n = 2; isset($taken[$slug]); $n++) {
                        $slug = $base.'-'.$n;
                    }

                    $taken[$slug] = true;
                    DB::table('awards')->where('id', $award->id)->update(['slug' => $slug]);
                }
            });
    }
};
