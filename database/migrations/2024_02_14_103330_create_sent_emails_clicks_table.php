<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use OriceOn\MailTracker\MailTracker;

return new class() extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->create('sent__emails__clicks', function (Blueprint $table) {
            $table->id();
            $table->uuid()->index();
            $table->foreignId('sent_email_id')->nullable()->constrained('sent__emails__lists')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('url', 2048)->nullable();
            $table->unsignedInteger('clicks')->nullable();
            $table->datetime('clicked_at')->nullable();
            $table->datetime('created_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->datetime('updated_at')->default(DB::raw('CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->drop('sent__emails__clicks');
    }
};
