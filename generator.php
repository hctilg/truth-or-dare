<?php

require_once 'database.php';

if (file_exists('database.db')) unlink('database.db');

try {
  $db = new Database();
  $db->init();
} catch (PDOException $e) {
  error_log("Error: " . $e->getMessage());
  exit;
}

foreach ([
  "truth_normal_boy" => "questions/truth_normal_boy.txt",
  "truth_normal_girl" => "questions/truth_normal_girl.txt",
  "truth_sexy_boy" => "questions/truth_sexy_boy.txt",
  "truth_sexy_girl" => "questions/truth_sexy_girl.txt",
  "dare_normal_boy" => "questions/dare_normal_boy.txt",
  "dare_normal_girl" => "questions/dare_normal_girl.txt",
  "dare_sexy_boy" => "questions/dare_sexy_boy.txt",
  "dare_sexy_girl" => "questions/dare_sexy_girl.txt"
] as $type => $path) {
  $file = fopen($path, 'r');
  
  while (($row = fgets($file)) !== false) {
    if (empty(trim($row))) continue;
    $question = str_replace(' \n ', "\n", $row);
    $question_b64 = base64_encode($question);
    $db->add_question("$type", $question_b64);
  }
  
  fclose($file);
}
