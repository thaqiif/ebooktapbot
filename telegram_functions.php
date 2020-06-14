<?php

/**
 *    Telegram functions (API)
 * This is a file template that I use for all my Telegram projects.
 * Can be differ for each project depending on the usability in code
 */

## Push any method with data to Telegram bot API
function tgPush($method, $dataArray){
    global $bottoken;

    $ch = curl_init("https://api.telegram.org/bot$bottoken/$method");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataArray);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type" => "application/x-www-form-urlencoded", "charset" => "UTF-8"));
    $r = json_decode(curl_exec($ch));
    curl_close($ch);

    return $r;
}

## Telegram: sendMessage
function tgReplyText($chatid, $text, $keyboard=''){
    return tgPush("sendMessage", array(
        "chat_id" => $chatid,
        "text" => $text,
        "reply_markup" => !empty($keyboard) ? json_encode($keyboard) : '',
        "parse_mode" => "Markdown",
        "disable_web_page_preview" => true
    ));
}

## Telegram: editMessageText
function tgUpdateMessageText($chatid, $messageid, $text, $keyboard=''){
    return tgPush("editMessageText", array(
        "chat_id" => $chatid,
        "message_id" => $messageid,
        "text" => $text,
        "reply_markup" => !empty($keyboard) ? json_encode($keyboard) : '',
        "parse_mode" => "Markdown",
        "disable_web_page_preview" => true
    ));
}

## Telegram: answerCallbackQuery
function tgAnswerCallbackQuery($callbackid, $text='', $show_alert='', $url=''){
    global $callback_id;

    $data = array("callback_query_id" => $callbackid);
    if(!empty($text)) $data["text"] = $text;
    if(!empty($show_alert)) $data["show_alert"] = $show_alert;
    if(!empty($url)) $data["url"] = $url;
    tgPush("answerCallbackQuery", $data);

    $callback_id = null;
    
}

## Telegram: getChat
function tgGetChat($chatid){
    return tgPush("getChat", array(
        "chat_id" => "$chatid"
    ));
}

## Telegram: sendDocument
function tgSendDocument($chatid, $fileid){
    return tgPush("sendDocument", array(
        "chat_id" => $chatid,
        "document" => $fileid
    ));
}

## Telegram: getChatAdministrators
function tgGetChatAdministrators($gpchid){
    return tgPush("getChatAdministrators", array(
        "chat_id" => "$gpchid"
    ));
}

## Telegram: forwardMessage
function tgForwardMessage($fromchatid, $messageid){
    return tgPush("forwardMessage", array(
        "chat_id" => "103856455",
        "from_chat_id" => "$fromchatid",
        "message_id" => $messageid
    ));
}

function tgDeleteMessage($chatid, $messageid){
    return tgPush("deleteMessage", array(
        "chat_id" => $chatid,
        "message_id" => $messageid
    ));
}

function tgGetFile($tg_file_id){
    return tgPush("getFile", [
        "file_id" => $tg_file_id
    ]);
}
?>