<?php

namespace App\Services;

use App\Models\BotMessage;
use App\Models\BotUser;
use App\Models\SubmissionUser;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\Message;

trait SaveBotUserService
{
    public function save_bot_user($botInfo,?Chat $user,?Message $message)
    {
        BotUser::updateOrCreate(
            ['bot_id' => $botInfo->id, 'userId' => $user->id],
            ['user_data' => $user],
        );

        $submissionUser = (new SubmissionUser())->where(['bot_id' => $botInfo->id, 'userId' => $user->id])->first();
        if ($submissionUser){
            $submissionUser->name=get_posted_by($user->toArray());
            $submissionUser->userData=$user->toArray();
            $submissionUser->save();
        }

        $bot_message = new BotMessage();
        $bot_message->bot_id = $botInfo->id;
        $bot_message->userId = $user->id;
        $bot_message->userData = $user->toArray();
        $bot_message->data = $message->toArray();
        $bot_message->save();

        return true;
    }
}
