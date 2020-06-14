<?php

require_once("../creds.php");
require_once("../helper_functions.php");
require_once("../telegram_functions.php");
require_once("../epubReader/process_epub_file.php");

## Constant value
$max_char_per_message = 610;

## Declare here for global access
$chat_id = null;
$callback_id = null;

## Get input from Telegram, convert and process it!
$tgInput = process_input(json_decode(file_get_contents("php://input")));

function process_input($tg){
    ## Check what type of input?
    if(isset($tg->message)){
        ## If messages
        process_message($tg);
    }else if(isset($tg->callback_query)){
        ## If from button (callback_query)
        process_callback_query($tg);
    }
}

function process_message($tg){
    global $db;
    global $chat_id;

    ## extract Telegram data
    $chat_id = $tg->message->chat->id;

    ## Check if it is text message
    if(isset($tg->message->text)){
        $text = $tg->message->text;

        ## Do the command checking
        if($text == "/start"){
            $responseText = "Hi, I'm ebook Tap! You send me e-book file (.epub) and I'll help you read by sending you a small section by section from the book. For the next section, click TAP!\n\nAnyway, if you want to give a try; here is a few books that is ready to read! Click on command.\n\nThe Silver Chair (C. S. Lewis)\nBook ID: /reuYwBPI";
            tgReplyText($chat_id, $responseText);
        }

        ## If the command starts with re at the front
        else if(startsWith($text, "/re")){
            ## We remove the front, get the book id at the back
            //$book_id = pg_escape_string(explode("/re", $text, 1)[1]);
            $book_id = pg_escape_string(str_replace("/re", "", $text));

            ## Check with database if exist.
            $is_book_exist = pg_query($db, "SELECT title, author, release_date, language, publisher, rights, isbn FROM books WHERE book_id='$book_id' AND is_ready=true");
            if($is_book_exist && pg_num_rows($is_book_exist) == 1){
                ## The book exist! We construct the detail of the book!
                $book_info = pg_fetch_object($is_book_exist);
                $title = escmd($book_info->title);
                $responseText =  "$title\n\n"
                                ."*Author:* ".escmd($book_info->author)."\n"
                                ."*Release date:* ".escmd($book_info->release_date)."\n"
                                ."*Lang:* ".escmd($book_info->language)."\n"
                                ."*Publisher:* ".escmd($book_info->publisher)."\n"
                                ."*Rights:* ".escmd($book_info->rights)."\n"
                                ."*ISBN:* ".escmd($book_info->isbn)."\n";

                $responseKeyboard = ["inline_keyboard" => [
                    [["text" => "Start Reading!", "callback_data" => "startRead_{$book_id}"]]
                ]];

                tgReplyText($chat_id, $responseText, $responseKeyboard);
            }else{
                $responseText = "The book with specified id not exist.";
                tgReplyText($chat_id, $responseText);
            }
        }
    }

    ## If not, probably it is media
    else{
        ## for media, we only accept document with mime_type: application/epub+zip
        if(isset($tg->message->document) && $tg->message->document->mime_type == "application/epub+zip"){

            ## For now, we only accept new epub from developer
            if($chat_id !== 103856455){
                tgReplyText($chat_id, "Sorry. As for now, only @thaqiif can add new ebook.");   ## My username on Telegram! 😁
                exit();
            }

            ## Check for filesize. The maximum can be 15MB
            if($tg->message->document->file_size <= 15728640){
                ## Get the tg_file_path
                $tg_get_file_object = tgGetFile($tg->message->document->file_id);
                $file_name = isset($tg->message->document->file_name) ? $tg->message->document->file_name : strval(time()).".epub";
                download_epub_from_tg_path($tg_get_file_object, $file_name);
            }else{
                $responseText = "Sorry, for now I only accept file with up to 15MB in size";
                tgReplyText($chat_id, $responseText);
            }
        }else{
            $responseText = "Sorry, I only receive document with .epub format";
            tgReplyText($chat_id, $responseText);
        }
    }
}

function process_callback_query($tg){
    global $db;
    global $chat_id;
    global $callback_id;
    global $max_char_per_message;

    $chat_id = $tg->callback_query->from->id;
    $callback_id = $tg->callback_query->id;
    $callback_data = $tg->callback_query->data;

    ## Using StartsWith function for checking!
    if(startsWith($callback_data, "startRead_")){
        ## If first tap

        $book_id = pg_escape_string(str_replace("startRead_", "", $callback_data)); ## get book_id
        $tap_message_object = constructTapMessage($book_id, 1);

        ## Get ready to send the first tap!
        $responseText = $tap_message_object->text;
        $responseKeyboard = ["inline_keyboard" => [
            [["text" => "TAP", "callback_data" => "contRead_{$book_id}_{$tap_message_object->next_line_id}"]]
        ]];

        tgReplyText($chat_id, $responseText, $responseKeyboard);
    }
    
    else if(startsWith($callback_data, "contRead_")){
        $params = explode("_", str_replace("contRead_", "", $callback_data));

        $book_id = $params[0];
        $next_line_id = $params[1];

        $tap_message_object = constructTapMessage($book_id, $next_line_id);
        
        $responseKeyboard = "";
        if($tap_message_object->next_line_id != -1 && $tap_message_object->text != ""){
            ## Not final
            ## Send the next tap!
            $responseText = $tap_message_object->text;
            $responseKeyboard = ["inline_keyboard" => [
                [["text" => "TAP", "callback_data" => "contRead_{$book_id}_{$tap_message_object->next_line_id}"]]
            ]];
        }else if($tap_message_object->next_line_id == -1 && $tap_message_object->text != ""){
            $responseText = $tap_message_object->text."\n\nEND";
        }else{
            $responseText = "END";
        }

        tgReplyText($chat_id, $responseText, $responseKeyboard);

    }

    if($callback_id != null){
        tgAnswerCallbackQuery($callback_id);
    }
}

function constructTapMessage($book_id, $start_line_id){
    global $db;
    global $max_char_per_message;

    $line_sent_draft = null;
    $line_char_count = 0;

    $current_line_id = $start_line_id;
    $is_get_next_line = true;
    
    while($is_get_next_line){
        $get_next_line = pg_query($db, "SELECT text FROM line_in_books WHERE book_id='$book_id' AND line_id=$current_line_id");
        if($get_next_line && pg_num_rows($get_next_line) == 1){
            $next_line = pg_fetch_object($get_next_line);

            $next_line_count = strlen($next_line->text);
            $next_line_text = escmd($next_line->text);

            if($line_sent_draft == null){
                ## No need to check if first time
                $line_sent_draft = $next_line_text;
                $line_char_count = $next_line_count;
                
                $current_line_id++;
            }else if($line_char_count + $next_line_count <= $max_char_per_message){
                $line_sent_draft .= "\n\n".$next_line_text;
                $line_char_count += $next_line_count;

                $current_line_id++;
            }else{
                $is_get_next_line = false;
            }
        }else{
            $current_line_id = -2;
        }
    }

    return ((Object)[
        "book_id" => $book_id,
        "next_line_id" => $current_line_id+1,
        "text" => $line_sent_draft
    ]);
}

function download_epub_from_tg_path($tg_get_file_object, $file_name){
    global $chat_id;
    global $bottoken;

    $file_path = $tg_get_file_object->result->file_path;
    $fileURL = "https://api.telegram.org/file/bot$bottoken/$file_path";

    $file_name = "../ebooks/$file_name";

    file_put_contents($file_name, file_get_contents($fileURL));
    
    ## Process the epub file!
    preprocess_epub_file($file_name);
}

?>