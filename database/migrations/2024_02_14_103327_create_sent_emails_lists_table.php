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
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->create('sent__emails__lists', function (Blueprint $table) {
            $table->id();
            $table->uuid()->index();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->json('recipient_to')->nullable();
            $table->json('recipient_cc')->nullable();
            $table->json('recipient_bcc')->nullable();
            $table->string('subject')->nullable();
            $table->longText('message')->nullable();
            $table->text('headers')->nullable();
            $table->text('meta')->nullable();
            $table->unsignedInteger('opens')->nullable();
            $table->unsignedInteger('clicks')->nullable();
            $table->datetime('opened_at')->nullable();
            $table->datetime('clicked_at')->nullable();
            $table->unsignedTinyInteger('type')->nullable();
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
        Schema::connection(MailTracker::sentEmailModel()->getConnectionName())->drop('sent__emails__lists');
    }
};
