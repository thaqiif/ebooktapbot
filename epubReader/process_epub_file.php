<?php

require_once("ebookRead.php");
require_once("ebookData.php");
require_once("../simple_html_dom/simple_html_dom.php");

$file_name_in_dir = null;

function preprocess_epub_file($file_name){
    global $db;
    global $chat_id;
    global $file_name_in_dir;
    $file_name_in_dir = $file_name;

    ## Read our epub file
    $ebook = new ebookRead($file_name);
    
    $ebook_metadata = [];
    $ebook_metadata["title"] = process_metadata($ebook->getDcTitle());
    $ebook_metadata["author"] = process_metadata($ebook->getDcCreator());
    $ebook_metadata["release_date"] = process_metadata($ebook->getDcDate());
    $ebook_metadata["language"] = process_metadata($ebook->getDcLanguage());
    $ebook_metadata["publisher"] = process_metadata($ebook->getDcPublisher());
    $ebook_metadata["rights"] = process_metadata($ebook->getDcRights());
    $ebook_metadata["ISBN"] = process_metadata($ebook->getDcIdentifier());

    ## Check if this ebook already in the database
    $check_if_already_exist = pg_query($db, "SELECT book_id FROM books WHERE upper(title)='".(strtoupper($ebook_metadata["title"]))."' AND upper(author)='".strtoupper($ebook_metadata["author"])."' AND is_ready=true");
    if($check_if_already_exist && pg_num_rows($check_if_already_exist) == 1){
        $ebook_metadata_constructed = constructMetadataTextAndSQL($ebook_metadata);
        $ebook_exist_id = pg_fetch_object($check_if_already_exist)->book_id;

        $responseText = $ebook_metadata_constructed->text."\nThe book already exist. The book ID: /re$ebook_exist_id\n\nHappy reading! 😁";
        tgReplyText($chat_id, $responseText);
        return;
    }

    ## Get Spine Info
    $ebookSpineInfo = $ebook->getSpine();

    ## Init the lineArrays for storing each line
    $lineArrays = [];
    ## Loop each spine and process it
    for($x = 0; $x < count($ebookSpineInfo); $x++){
        $contents = htmlRemoverByContent($ebook->getContentById($ebookSpineInfo[$x]), $ebook); ## HTML Remover
		$html = str_get_html($contents);    ## Create HTML DOM from String
        ## Extract only text from the processed HTML
		foreach($html->find("h1, h2, h3, h4, h5, h6, p") as $p){
			$lineArrays[] = trim($p->plaintext);
		}
    }
    
    ## After everything is done. We now check and proceed to storing into database
    ## Init variable first
    $lineArraysCount = count($lineArrays);
    if($lineArraysCount > 50){
        ## We only store if the content is more than 50
        storeEpubInfoIntoDatabase($ebook_metadata, $lineArrays);

    }else{
        ## We will return message to the user saying its fail.
        tgReplyText($chat_id, "The epub file cannot be processed.");
    }
}

function process_metadata($data){
	$info = "";
	if(is_array($data)){
		foreach($data as $element){
			if($info == "")
				$info = $element;
			else
				$info = $info.", ".$element;
		}
		$data = $info;
    }
    
    if($data != "") return strval($data);
    return null;
}

function htmlRemoverByContent($content, $ebook){

    // remove linebreaks (no multi line matches in JS regex!)
    $result = preg_replace("/\r?\n/", "\u0000", $content);

    // keep only <body> contents
    $match = [];
    preg_match('/<body[^>]*?>(.*)<\/body[^>]*?>/i', $result, $match);
    $result = trim($match[1]);

    // remove <script> blocks if any
    $result = preg_replace('/<script[^>]*?>(.*?)<\/script[^>]*?>/i', '', $result);

    // remove <style> blocks if any
    $result = preg_replace('/<style[^>]*?>(.*?)<\/style[^>]*?>/i', '', $result);

    // remove onEvent handlers
    $result = preg_replace_callback('/(\s)(on\w+)(\s*=\s*["\']?[^"\'\s>]*?["\'\s>])/', function($matches){
        return $matches[1]."skip-".$matches[2].$matches[3];
    }, $result);

    /*/ replace images
    $result = preg_replace_callback('/(\s(?:xlink:href|src)\s*=\s*["\']?)([^"\'\s>]*?)(["\'\s>])/', function($matches) use($chapterHref){
        $img = Util::directoryConcat($chapterHref, urldecode($matches[2]), True);

        $element = null;
        foreach ($this->manifest as $key => $value) {
            $mainestUrl = $value['href'];
            if ($mainestUrl === $img) {
                $element = $value;
                break;
            }
        }
        if (!is_null($element)) {
            return $matches[1].$this->imageWebRoot.'/'.$img.$matches[3];
        }
        return '';
    }, $result);

    // links
    $result = preg_replace_callback('/(\shref\s*=\s*["\']?)([^"\'\s>]*?)(["\'\s>])/', function($matches) use($chapterHref){
        $linkparts = isset($matches[2]) ? explode("#", Util::directoryConcat($chapterHref, urldecode($matches[2]), true)): [];
        $link    = array_shift($linkparts) ?? '';
        $element   = null;

        if ($link) {
            foreach ($this->manifest as $key => $value) {
                if(explode('#', $value['href'])[0] === $link) {
                    $element = $value;
                    break;
                }
            }
        }

        if (count($linkparts)) {
            $link .= '#'.implode( '#',$linkparts);
        }

        // include only images from manifest
        if ($element) {
            return $matches[1].$this->linkWebRoot."/".$link.$matches[3];
        }
        return $matches[1].$matches[2].$matches[3];
    }, $result);
    */

    return preg_replace("/\\\u0000/", "\n",  $result);
}

function storeEpubInfoIntoDatabase($ebook_metadata, $lineArrays){
    global $db;
    global $chat_id;

    ## Init variable for showing progress to user
    $totalLines = count($lineArrays);
    $tg_message_id = null;

    ## Construct ebook_metadata text
    $ebook_metadata_constructed = constructMetadataTextAndSQL($ebook_metadata);

    $book_id = generateBookID();

    ## Saved the ebook metadata to database
    $insertEbookMetadataSQL = "INSERT INTO books (book_id, {$ebook_metadata_constructed->key_sql} is_ready) VALUES ('$book_id', $ebook_metadata_constructed->value_sql false)";
    $insertEbookMetadata = pg_query($db, $insertEbookMetadataSQL);

    ## if successful
    if($insertEbookMetadata){
        $ebook_metadata_text = $ebook_metadata_constructed->text."\nProcessing...";
        $res = tgReplyText($chat_id, $ebook_metadata_text);
        
        if($res->ok){
            $tg_message_id = $res->result->message_id;
            
            ## We start to save one by one line
            foreach($lineArrays as $index => $value){
                $line = $index+1;
                $text = pg_escape_string($value);
                $s = pg_query($db, "INSERT INTO line_in_books (book_id, line_id, text) VALUES ('$book_id', $line, '$text')");

                if(!$s){
                    $errorText = $ebook_metadata_constructed->text."\nThere's an error.";
                    tgUpdateMessageText($chat_id, $tg_message_id, "`".pg_last_error($db)."`");
                    exit(); ## Exit program
                }

                if($line % 25 == 0){
                    ## Showing percent!
                    $progressText = $ebook_metadata_constructed->text."\nProcessing... ".intval($line/$totalLines*100)."%";
                    tgUpdateMessageText($chat_id, $tg_message_id, $progressText);
                }
            }

            ## Update the is_ready column
            $is_ready = pg_query($db, "UPDATE books SET is_ready=true WHERE book_id='$book_id'");
            if($is_ready){
                $progressTextFinal = $ebook_metadata_constructed->text."\nDone! This is your book ID: /re$book_id\n\nHappy reading! 😁";
                tgUpdateMessageText($chat_id, $tg_message_id, $progressTextFinal);

                ## Remove from directory
                if(is_file($file_name_in_dir)) unlink($file_name_in_dir);
            }else{
                $errorText = $ebook_metadata_constructed->text."\nThere's an error.";
                tgUpdateMessageText($chat_id, $tg_message_id, $errorText);
                exit(); ## Exit program
            }
        }
    }else{
        tgReplyText($chat_id, "`".pg_last_error($db)."`");
    }
}

function constructMetadataTextAndSQL($ebook_metadata){
    $text = "";
    $key_sql = "";
    $value_sql = "";

    foreach($ebook_metadata as $key => $value){
        if($value != null){
            $text .= "*$key:* ".escmd($value)."\n";
            $key_sql .= "$key, ";
            $value_sql .= "'".pg_escape_string($value)."', ";
        }
    }

    return ((Object)[
        "text" => $text,
        "key_sql" => $key_sql,
        "value_sql" => $value_sql
    ]);
}

?>