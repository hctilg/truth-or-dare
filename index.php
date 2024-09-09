<?php

/********************** Importing Requirements **********************/

$telebot_path = 'telebot@2.1.php';

// checking the exists "Telebot Library".
if (!file_exists($telebot_path)) {
  copy('https://raw.githubusercontent.com/hctilg/telebot/v2.1/index.php', $telebot_path);
}

require_once 'config.php';
require_once 'database.php';

// import telebot library
require_once $telebot_path;

/*********************** Main Section of Code ***********************/

$bot = new Telebot(TOKEN, false);

try {
  // Create a Sqlite database.
  $db = new Database();
  $db->init();
} catch (PDOException $e) {
  $msg = $bot->sendMessage(['chat_id'=> CREATOR, 'text'=> "⚠️ Connection to database failed!"]);
  $bot->sendMessage(['chat_id'=> CREATOR, 'text'=> 'Error: ' . $e->getMessage(), 'reply_to_message_id'=> $msg['result']['message_id']]);
  error_log("Error: " . $e->getMessage());
  exit;
}

$bot->on('text', function($data) use ($bot, $db) {
  $chat_type = $data['chat']['type'] ?? $data['chat_type'];
  $chat_id = $data['chat']['id'] ?? $data['from']['id'];
  $name = $data['chat']['first_name'];
  if (isset($data['chat']['last_name'])) $name .= $data['chat']['last_name'];
  $msg_id = $data['message_id'] ?? -1;
  $text = $data['text'] ?? '';
  
  if ($chat_type != 'private') return;
  
  $is_new = $db->add_user($chat_id);
  $user = $db->get_user($chat_id);

  if ($chat_id == CREATOR && startsWith('/backup', $text)) {
    $bot->sendDocument([ 'chat_id'=> $chat_id, 'document'=> 'database.db', 'reply_to_message_id'=> $msg_id ]);
    return;
  }

  if (startsWith("get_question_", $user['step'])) {
    if (mb_strlen("$text") > 200 || mb_strlen("$text") < 5) {
      $bot->sendMessage(['chat_id'=> $chat_id, 'text'=> "طول سوال باید بین ۵ تا ۲۰۰ کرکتر باشه!"]);
      return;
    }

    $type = substr($user['step'], strlen('get_question_'));

    $db->change_user($chat_id, 'step', 'main');

    if ($chat_id == CREATOR) {
      $db->add_question("$type", base64_encode("$text"));
      $bot->sendMessage([
        'chat_id'=> $chat_id,
        'text'=> "در دیتابیس ثبت شد.",
        'reply_markup'=> Telebot::inline_keyboard("[برگشت ➡️|back_to_home]"),
        'reply_to_message_id'=> $msg_id
      ]);
    } else {
      $bot->sendMessage([
        'chat_id'=> $chat_id,
        'text'=> "سوال شما ثبت شد و پس از تایید مدیران ربات به ربات اضافه خواهد شد.",
        'reply_markup'=> Telebot::inline_keyboard("[برگشت ➡️|back_to_home]"),
        'reply_to_message_id'=> $msg_id
      ]);

      $genre = [
        "truth_normal_boy" => "حقیقت عادی (پسر)",
        "truth_normal_girl" => "حقیقت عادی (دختر)",
        "truth_sexy_boy" => "حقیقت +18 (پسر)",
        "truth_sexy_girl" => "حقیقت +18 (دختر)",
        "dare_normal_boy" => "جرأت عادی (پسر)",
        "dare_normal_girl" => "جرأت عادی (دختر)",
        "dare_sexy_boy" => "جرأت +18 (پسر)",
        "dare_sexy_girl" => "جرأت +18 (دختر)"
      ][$type];

      $bot->sendMessage([
        'chat_id'=> CREATOR,
        'text'=> "سوال ارسالی در ژانر ${genre} :\n\n${text}",
        'reply_markup'=> Telebot::inline_keyboard("[لغو ❌|reject][تایید ✅|add_${type}]"),
      ]);
    }
  } else {
    $bot->sendMessage([
      'chat_id'=> $chat_id,
      'text'=> trim("
سلام کاربر «${name}» گرامی  به ربات جرات و حقیقت خوش آمدی 😈
    
    • اگه میخوای بازی رو شروع کنی روی دکمه 'بازی با دوستان' بزن 😉👊🏻

    راستی اگه سوالی به ذهنت رسید و خواستی اضافه کنی میتونی از دکمه ثبت سوال استفاده کنی 😍✌🏻
"),
      'reply_markup'=> Telebot::inline_keyboard("[ثبت سوال 📥|send_question][بازی با دوستان 🔥|switch_inline_query:]"),
      'reply_to_message_id'=> $msg_id
    ]);
  } 
});

$bot->on('inline_query', function($inline_query) use ($bot, $db) {
  $chat_type = $inline_query['chat_type'];
  $query_data = $inline_query['query'];
  $query_id = $inline_query['id'];
  $chat_id = $inline_query['from']['id'] ?? $inline_query['chat']['id'];
  $name = $inline_query['from']['first_name'];
  if (isset($inline_query['from']['last_name'])) $name .= $inline_query['from']['last_name'];

  $bot_id = $bot("getMe")['result']['id'];
  
  $file_path = $bot->getFile(['file_id' => max($bot->getUserProfilePhotos(['user_id'=> $bot_id])['result']['photos'][0])['file_id']])['result']['file_path'];
  
  $bot_profile = "https://api.telegram.org/file/bot" . TOKEN . "/$file_path";
    
   $bot->answerInlineQuery([
     'cache_time' => '2',
     'inline_query_id' => $query_id,
     'results' => json_encode([[
        'id'=> '0',
        'type'=> 'article',
        'title'=> '🍾 برای شروع بازی جرعت یا حقیقت کلیک کنید',
        'description'=> 'با کلیک روی این دکمه یک پیام به گروه / پیوی مورد نظر ارسال میشه که میتونید بصورت دونفره یا گروهی با دوستاتون بازی کنید.',
        'thumbnail_url'=> $bot_profile,
        'message_text'=> "
سلام سلام 😃👐🏻
بیاید جرأت حقیقت بازی کنیم 🤤

🙋🏻 کی پایست بازی کنیم 🙋🏻‍♂️

اگه پایه ای بزن رو دکمه زیر تا به بازی اضافتون کنم 🤫

اعضای چالش :
1. ${name}",
        'reply_markup'=> json_decode(Telebot::inline_keyboard("[من پایه ام 🖐🏻|new_player_${chat_id}_0][شروع بازی 🔥|play_${chat_id}_0]")),
        'disable_web_page_preview' => true
    ]])
  ]);  
});

function get_name($bot, $user_id) {
  $chat = $bot->getChat(['chat_id'=> $user_id])['result'];
  $chat_name = $chat['first_name'] ?? '';
  if (isset($chat['last_name'])) $chat_name .= $chat['last_name'];
  return $chat_name;
}

$bot->on('callback_query', function($callback_query) use ($bot, $db) {
  $query_id = $callback_query['id'];
  $query_data = $callback_query['data'];
  $chat_id = $callback_query['chat']['id'] ?? $callback_query['from']['id'];
  $msg_id = $callback_query['message']['message_id'] ?? -1;
  $name = $callback_query['from']['first_name'];
  if (isset($data['from']['last_name'])) $name .= $data['from']['last_name'];

  if ($query_data == 'send_question') {
    $db->change_user($chat_id, 'step', 'main');
    $bot->editMessageText([
      'chat_id'=> $chat_id,
      'text'=> "قصد دارید برای کدام یک از دسته های زیر سوال ارسال کنید؟",
      'reply_markup'=> Telebot::inline_keyboard('
        [حقیقت عادی (پسر)|truth_normal_boy][حقیقت عادی (دختر)|truth_normal_girl]
        [حقیقت +18 (پسر)|truth_sexy_boy][حقیقت +18 (دختر)|truth_sexy_girl]
        [جرأت عادی (پسر)|dare_normal_boy][جرأت عادی (دختر)|dare_normal_girl]
        [جرأت +18 (پسر)|dare_sexy_boy][جرأت +18 (دختر)|dare_sexy_girl]
        [برگشت ➡️|back_to_home]'),
      'message_id'=> $msg_id
    ]);
  }

  if (in_array($query_data, [
    "truth_normal_boy", "truth_normal_girl", "truth_sexy_boy", "truth_sexy_girl",
    "dare_normal_boy", "dare_normal_girl", "dare_sexy_boy", "dare_sexy_girl"
  ])) {
    $db->change_user($chat_id, 'step', "get_question_$query_data");
    $bot->editMessageText([
      'chat_id'=> $chat_id,
      'text'=> "سوال خود را ارسال کنید👇🏻",
      'reply_markup'=> Telebot::inline_keyboard('[برگشت ➡️|send_question]'),
      'message_id'=> $msg_id
    ]);
  }

  if ($query_data == 'back_to_home') {
    $db->change_user($chat_id, 'step', 'main');
    $bot->editMessageText([
      'chat_id'=> $chat_id,
      'text'=> trim("
سلام کاربر «${name}» گرامی  به ربات جرات و حقیقت خوش آمدی 😈
    
    • اگه میخوای بازی رو شروع کنی روی دکمه 'بازی با دوستان' بزن 😉👊🏻

    راستی اگه سوالی به ذهنت رسید و خواستی اضافه کنی میتونی از دکمه ثبت سوال استفاده کنی 😍✌🏻
"),
      'reply_markup'=> Telebot::inline_keyboard("[ثبت سوال 📥|send_question][بازی با دوستان 🔥|switch_inline_query:]"),
      'message_id'=> $msg_id
    ]);
  }

  if (startsWith('add_', $query_data)) {
    $type = substr($query_data, strlen('add_'));
    
    $text_lines = explode("\n", $callback_query['message']['text']);
    unset($text_lines[0]);
    
    $question = trim(join("\n", $text_lines));

    $db->add_question("$type", base64_encode("$question"));
    $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "سوال به دیتابیس اصافه شد 🎈", 'show_alert'=> true]);
    
    $genre = [
      "truth_normal_boy" => "حقیقت عادی (پسر)",
      "truth_normal_girl" => "حقیقت عادی (دختر)",
      "truth_sexy_boy" => "حقیقت +18 (پسر)",
      "truth_sexy_girl" => "حقیقت +18 (دختر)",
      "dare_normal_boy" => "جرأت عادی (پسر)",
      "dare_normal_girl" => "جرأت عادی (دختر)",
      "dare_sexy_boy" => "جرأت +18 (پسر)",
      "dare_sexy_girl" => "جرأت +18 (دختر)"
    ][$type];


    $bot->editMessageText([
      'chat_id'=> $chat_id,
      'text'=> "
⚜️ دسته‌بندی: {$genre}

  • ${question}
",
      'reply_markup'=> Telebot::inline_keyboard(''),
      'message_id'=> $msg_id
    ]);
  }

  if ($query_data == 'reject') {
    $bot->deleteMessage(['chat_id'=> $chat_id, 'message_id'=> $msg_id]);
  }

  if (startsWith('new_player_', $query_data)) {
    $metadata = explode('_', substr($query_data, strlen('new_player_')));
    $game_starter = $metadata[0];
    $is_game_new = $metadata[1]  == '0';

    if ($is_game_new) {
      $pid =  md5(microtime());
      $data = [
        'players'=> [$game_starter]
      ];
      $db->game_start($pid, $data);
    } else {
      $pid = $metadata[2];
      $data = $db->get_game($pid);
    }

    if (in_array($chat_id, $data['players'])) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "شما از قبل در بازی حضور داشتید ، لطفا منتظر شروع بازی بمانید.", 'show_alert'=> true]);
      return;
    }

    $data['players'][] = $chat_id;
    $db->game_update($pid, $data);

    $names = '';
    foreach ($data['players'] as $i => $player) {
      $i++;
      $chat_name = get_name($bot, $player);
      $names .= "$i. $chat_name \n";
    }

    $bot->editMessageText([
      'text'=> "
سلام سلام 😃👐🏻
بیاید جرأت حقیقت بازی کنیم 🤤

🙋🏻 کی پایست بازی کنیم 🙋🏻‍♂️

اگه پایه ای بزن رو دکمه زیر تا به بازی اضافتون کنم 🤫

اعضای چالش :
${names}",
      'reply_markup'=> Telebot::inline_keyboard("[من پایه ام 🖐🏻|new_player_${game_starter}_1_${pid}][شروع بازی 🔥|play_${game_starter}_1_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('play_', $query_data)) {
    $metadata = explode('_', substr($query_data, strlen('play_')));
    $game_starter = $metadata[0];
    $is_game_new = $metadata[1]  == '0';
  
    if ($chat_id != $game_starter) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "شما سازنده این بازی نیستید.", 'show_alert'=> true]);
      return;
    }
  
    if ($is_game_new) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "برای شروع بازی باید حدقل دو نفر داخل بازی باشن.", 'show_alert'=> true]);
      return;
    }
    
    $pid = $metadata[2];
    $data = $db->get_game($pid);

    $round = 0;
    $current_player = get_name($bot, $data['players'][$round]);

    $data['round'] = $round;
    $data['starter'] = $game_starter;
    $db->game_update($pid, $data);

    $bot->editMessageText([
      'text'=> "
نوبت  «${current_player}»  هست 😙

جرأت یا حقیقت ؟ 🙄",
      'reply_markup'=> Telebot::inline_keyboard("[حقیقت 😇|q_truth_${pid}][جرأت 😈|q_dare_${pid}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]); 
  }

  if (startsWith('q_truth_', $query_data)) {
    $pid = substr($query_data, strlen('q_truth_'));
    $data = $db->get_game($pid);

    if ($chat_id != $data['players'][$data['round']]) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "نوبت شما نیست.", 'show_alert'=> true]);
      return;
    }

    $current_player = get_name($bot, $data['players'][$data['round']]);

    $bot->editMessageText([
      'text'=> "
> نوبت : ${current_player}
📍 نوع سوال و جنسیت خودت رو انتخاب کن :",
      'reply_markup'=> Telebot::inline_keyboard("[حقیقت عادی (🙍🏻‍♂️)|run_${pid},truth_normal_boy][حقیقت +18 (🙍🏻‍♂️)|run_${pid},truth_sexy_boy]\n[حقیقت عادی (🙎🏻‍♀️)|run_${pid},truth_normal_girl][حقیقت +18 (🙎🏻‍♀️)|run_${pid},truth_sexy_girl]\n[برگشت|back_to_game_${pid}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('q_dare_', $query_data)) {
    $pid = substr($query_data, strlen('q_dare_'));
    $data = $db->get_game($pid);

    if ($chat_id != $data['players'][$data['round']]) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "نوبت شما نیست.", 'show_alert'=> true]);
      return;
    }

    $current_player = get_name($bot, $data['players'][$data['round']]);

    $bot->editMessageText([
      'text'=> "
> نوبت : ${current_player}
📍 نوع سوال و جنسیت خودت رو انتخاب کن :",
      'reply_markup'=> Telebot::inline_keyboard("[جرأت عادی (🙍🏻‍♂️)|run_${pid},dare_normal_boy][جرأت +18 (🙍🏻‍♂️)|run_${pid},dare_sexy_boy]\n[جرأت عادی (🙎🏻‍♀️)|run_${pid},dare_normal_girl][جرأت +18 (🙎🏻‍♀️)|run_${pid},dare_sexy_girl]\n[برگشت|back_to_game_${pid}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('back_to_game_', $query_data)) {
    $pid = substr($query_data, strlen('back_to_game_'));
    $data = $db->get_game($pid);

    if ($chat_id != $data['players'][$data['round']]) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "نوبت شما نیست.", 'show_alert'=> true]);
      return;
    }

    $current_player = get_name($bot, $data['players'][$data['round']]);

    $bot->editMessageText([
      'text'=> "
نوبت  «${current_player}»  هست 😙

جرأت یا حقیقت ؟ 🙄",
      'reply_markup'=> Telebot::inline_keyboard("[حقیقت 😇|q_truth_${pid}][جرأت 😈|q_dare_${pid}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('run_', $query_data)) {
    $metadata = explode(',', substr($query_data, strlen('run_')));
    $pid = $metadata[0];
    $data = $db->get_game($pid);

    if ($chat_id != $data['players'][$data['round']]) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "نوبت شما نیست.", 'show_alert'=> true]);
      return;
    }

    $type = $metadata[1];

    $genre = [
      "truth_normal_boy" => "حقیقت عادی (پسر)",
      "truth_normal_girl" => "حقیقت عادی (دختر)",
      "truth_sexy_boy" => "حقیقت +18 (پسر)",
      "truth_sexy_girl" => "حقیقت +18 (دختر)",
      "dare_normal_boy" => "جرأت عادی (پسر)",
      "dare_normal_girl" => "جرأت عادی (دختر)",
      "dare_sexy_boy" => "جرأت +18 (پسر)",
      "dare_sexy_girl" => "جرأت +18 (دختر)"
    ][$type];

    $current_player = get_name($bot, $data['players'][$data['round']]);

    $question = trim($db->random_question($type)) ?? "شانس آوردی پوچ شد، رد کن 😁";

    $bot->editMessageText([
      'text'=> "
👤 نوبت : ${current_player}
🎭 نوع سوال : ${genre}
📝 سوال :
${question}

--------------------
بعد از اینکه به سوال بالا جواب دادی روی گزینه [پاسخ دادم ✅] کلیک کن.",
      'reply_markup'=> Telebot::inline_keyboard("[پاسخ دادم ✅|answered_${pid}][تغییر سوال ♻️|${query_data}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('answered_', $query_data)) {
    $pid = substr($query_data, strlen('answered_'));
    $data = $db->get_game($pid);

    if ($chat_id != $data['players'][$data['round']]) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "نوبت شما نیست.", 'show_alert'=> true]);
      return;
    }

    $data['round'] = ($data['round'] >= (count($data['players']) - 1)) ? 0 : ($data['round'] + 1);
    $db->game_update($pid, $data);

    $current_player = get_name($bot, $data['players'][$data['round']]);

    $bot->editMessageText([
      'text'=> "
نوبت  «${current_player}»  هست 😙

جرأت یا حقیقت ؟ 🙄",
      'reply_markup'=> Telebot::inline_keyboard("[حقیقت 😇|q_truth_${pid}][جرأت 😈|q_dare_${pid}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('skip_', $query_data)) {
    $pid = substr($query_data, strlen('skip_'));
    $data = $db->get_game($pid);

    if ($chat_id != $data['starter']) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "شما سازنده این بازی نیستید.", 'show_alert'=> true]);
      return;
    }

    $data['round'] = ($data['round'] >= (count($data['players']) - 1)) ? 0 : ($data['round'] + 1);
    $db->game_update($pid, $data);

    $current_player = get_name($bot, $data['players'][$data['round']]);

    $bot->editMessageText([
      'text'=> "
نوبت  «${current_player}»  هست 😙

جرأت یا حقیقت ؟ 🙄",
      'reply_markup'=> Telebot::inline_keyboard("[حقیقت 😇|q_truth_${pid}][جرأت 😈|q_dare_${pid}]\n[👇🏻 - دستورات مخصوص سازنده بازی - 👇🏻|null]\n[رد کردن این شخص ♻️|skip_${pid}][اتمام بازی ❗️|end_game_${pid}]"),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }

  if (startsWith('end_game_', $query_data)) {
    $pid = substr($query_data, strlen('end_game_'));
    $data = $db->get_game($pid);

    if ($chat_id != $data['starter']) {
      $bot->answerCallbackQuery(['callback_query_id'=> $query_id, 'text'=> "شما سازنده این بازی نیستید.", 'show_alert'=> true]);
      return;
    }

    $db->game_end($pid);
    $bot->editMessageText([
      'text'=> "بازی توسط سازنده با موفقیت بسته شد.",
      'reply_markup'=> Telebot::inline_keyboard(''),
      'inline_message_id'=> $callback_query['inline_message_id']
    ]);
  }
});

$bot->run();
