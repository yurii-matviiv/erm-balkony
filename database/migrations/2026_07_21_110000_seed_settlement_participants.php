<?php

use App\Models\AppSetting;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed the settlement participants (Юрій, Сергій) so the "+ Переказ …"
 * buttons and per-participant indicators work out of the box — the user
 * expects three buttons immediately, not a manual setup step.
 *
 * Resolved by EMAIL, not by id: emails are stable across environments
 * (local/production get different auto-increment ids from sync), and
 * hardcoding ids would violate the "жодних зашитих цифр" principle.
 * The "Учасники" header action on the page remains the way to change
 * this later without a developer.
 *
 * Confirmed by the user (2026-07-21): Сергій = serhiy.kuts@… (роль
 * "Керівник компанії"), NOT the installer "Сергей Куц" (kutsergg@…).
 */
return new class extends Migration
{
    /** Order matters: it defines button/indicator order (Юрій first). */
    private const PARTICIPANT_EMAILS = [
        'admin@gmail.com',
        'serhiy.kuts@balkony-vikna.kyiv.ua',
    ];

    public function up(): void
    {
        // Don't overwrite a configuration someone already made by hand.
        if (filled(AppSetting::get(Settlement::PARTICIPANTS_SETTING))) {
            return;
        }

        $ids = collect(self::PARTICIPANT_EMAILS)
            ->map(fn (string $email) => User::where('email', $email)->value('id'))
            ->filter()
            ->values()
            ->all();

        if ($ids !== []) {
            Settlement::saveParticipants($ids);
        }
    }

    public function down(): void
    {
        AppSetting::where('key', Settlement::PARTICIPANTS_SETTING)->delete();
    }
};
