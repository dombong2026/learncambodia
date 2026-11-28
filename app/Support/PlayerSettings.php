<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class PlayerSettings
{
    public const AUDIO_PLAYERS = ['premium' => 'Sonic Player', 'native' => 'Browser audio player'];

    public static function audioPlayer(): string
    {
        return DB::table('player_settings')->where('id', 1)->value('audio_player') ?? 'premium';
    }

    public static function updateAudioPlayer(string $mode): void
    {
        DB::table('player_settings')->upsert(
            [['id' => 1, 'audio_player' => $mode]], ['id'], ['audio_player']
        );
    }
}
