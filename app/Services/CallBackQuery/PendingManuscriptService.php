<?php

namespace App\Services\CallBackQuery;

use App\Enums\KeyBoardData;
use App\Enums\ManuscriptStatus;
use App\Models\Manuscript;
use App\Services\SendTelegramMessageService;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message;

class PendingManuscriptService
{
    use SendTelegramMessageService;

    public function refresh(Api $telegram, $botInfo,$chatId,$messageId,Message $message,$callbackQueryId)
    {
        $inline_keyboard=[
            'inline_keyboard' => [
                [
                    [
                        'text' => '刷新 🔄',
                        'callback_data' => 'refresh_pending_manuscript_list',
                    ],
                ],
            ],
        ];

        $manuscript = (new \App\Models\Manuscript())->where('bot_id', $botInfo->id)->where('status', ManuscriptStatus::PENDING)->get();
        if (!$manuscript->isEmpty()){
            foreach ($manuscript as $item){
                $inline_keyboard['inline_keyboard'][] = [
                    [
                        'text' => "【".$item->text."】",
                        'callback_data' => 'show_pending_manuscript:'.$item->id,
                    ],
                ];
            }
        }

        if ($message->replyMarkup){
            $messageInlineKeyboard = json_decode($message->replyMarkup,true);
            //检查是否与当前的inline_keyboard一致
            if ($messageInlineKeyboard == $inline_keyboard){
                try {
                    $telegram->answerCallbackQuery([
                        'callback_query_id' => $callbackQueryId,
                        'text' => '暂无新稿件',
                        'show_alert' => true,
                    ]);
                    return 'ok';
                } catch (TelegramSDKException $telegramSDKException) {
                    Log::error($telegramSDKException);
                    return 'error';
                }
            }
        }

        try {
            $telegram->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => json_encode($inline_keyboard),
            ]);
            return 'ok';
        } catch (TelegramSDKException $telegramSDKException) {
            Log::error($telegramSDKException);
            return 'error';
        }
    }

    public function show(Api $telegram, $botInfo,?Manuscript $manuscript): string
    {
        //机器人的审核数
        $review_num = $botInfo->review_num;
        //稿件ID
        $manuscriptId = $manuscript->id;
        //通过人员名单
        $approved = $manuscript->approved;
        //通过人员数量
        $approvedNum = count($approved);
        //拒绝人员名单
        $reject = $manuscript->reject;
        //拒绝人员数量
        $rejectNum = count($reject);

        $inline_keyboard=null;

        if ($approvedNum>=$review_num || $rejectNum>=$review_num){
            if ($approvedNum>=$review_num) {
                $inline_keyboard=KeyBoardData::REVIEW_GROUP_APPROVED;
                $inline_keyboard['inline_keyboard'][0][0]['callback_data'] .= ":".$manuscript->id;
                $inline_keyboard['inline_keyboard'][0][1]['url'] .= $botInfo->channel->name."/".$manuscript->message_id;
                $inline_keyboard['inline_keyboard'][1][0]['callback_data'] .= ':'.$manuscript->id;
            }elseif ($rejectNum>=$review_num){
                $inline_keyboard=KeyBoardData::REVIEW_GROUP_REJECT;
                $inline_keyboard['inline_keyboard'][0][0]['callback_data'] .= ":".$manuscript->id;
            }
        }else{
            $inline_keyboard = KeyBoardData::REVIEW_GROUP;

            $inline_keyboard['inline_keyboard'][0][0]['text'] .= "($approvedNum/$review_num)";
            $inline_keyboard['inline_keyboard'][0][0]['callback_data'] .= ":$manuscriptId";

            $inline_keyboard['inline_keyboard'][0][1]['text'] .= "($rejectNum/$review_num)";
            $inline_keyboard['inline_keyboard'][0][1]['callback_data'] .= ":$manuscriptId";

            $inline_keyboard['inline_keyboard'][0][2]['callback_data'] .= ":$manuscriptId";

            $inline_keyboard['inline_keyboard'][1][0]['callback_data'] .= ":$manuscriptId";
            $inline_keyboard['inline_keyboard'][1][1]['callback_data'] .= ":$manuscriptId";
        }

        // 发送消息到审核群组
        $this->sendGroupMessage($telegram, $botInfo, $manuscript->data, $manuscript->type, $manuscript->id,$inline_keyboard);
        return 'ok';
    }
}
