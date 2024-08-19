<?php

/***************************** Database *****************************/

class Database {
  public $connection = null;
  public function __construct() {
    // sudo pacman -S php-sqlite3
    $this->connection = new PDO("sqlite:database.db");
    $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function init() {
    $this->connection->query("CREATE TABLE IF NOT EXISTS `users` (`id` TEXT NOT NULL , `step` LONGTEXT NOT NULL );");
    $this->connection->query("CREATE TABLE IF NOT EXISTS `questions` (`type` TEXT NOT NULL , `question` LONGTEXT NOT NULL );");
    $this->connection->query("CREATE TABLE IF NOT EXISTS `games` (`id` TEXT NOT NULL , `data` LONGTEXT NOT NULL );");
  }

  public function game_exists($pid) {
    $stmt = $this->connection->prepare("SELECT * FROM `games` WHERE `id` LIKE :id");
    $stmt->bindParam(':id', $pid);
    $stmt->execute();
    return !!$stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function game_start($pid, $data) {
    if ($this->game_exists($pid)) return false;

    $jdata = json_encode($data);
    
    $stmt = $this->connection->prepare("INSERT INTO `games` (`id`, `data`) VALUES (:id, :data)");
    $stmt->bindParam(':id', $pid, PDO::PARAM_STR);
    $stmt->bindParam(':data', $jdata, PDO::PARAM_STR);
    $stmt->execute();
    return true;
  }

  public function get_game($pid) {
    if (!$this->game_exists($pid)) return false;

    $stmt = $this->connection->prepare("SELECT * FROM `games` WHERE `id` = :id");
    $stmt->bindParam(':id', $pid);
    $stmt->execute();

    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    return json_decode($game['data'], true);
  }

  public function game_update($pid, $data) {
    $jdata = json_encode($data);
    $stmt = $this->connection->prepare("UPDATE `games` SET `data` = :data WHERE `id` = :id;");
    $stmt->bindParam(':id', $pid, PDO::PARAM_STR);
    $stmt->bindParam(':data', $jdata, PDO::PARAM_STR);
    $stmt->execute();
    return true;
  }

  public function game_end($pid) {
    $stmt = $this->connection->prepare("DELETE FROM `games` WHERE `id` = :id");
    $stmt->bindParam(':id', $pid, PDO::PARAM_STR);
    $stmt->execute();
    return true;
  }

  public function user_exists($chat_id) {
    $hash_id = md5($chat_id);

    $stmt = $this->connection->prepare("SELECT * FROM `users` WHERE `id` LIKE :id");
    $stmt->bindParam(':id', $hash_id);
    $stmt->execute();
    return !!$stmt->fetch(PDO::FETCH_ASSOC);
  }

  public function add_user($chat_id) {
    if ($this->user_exists($chat_id)) return false;

    $hash_id = md5($chat_id);
    $step = "main";
    
    $stmt = $this->connection->prepare("INSERT INTO `users` (`id`, `step`) VALUES (:id, :step)");
    $stmt->bindParam(':id', $hash_id, PDO::PARAM_STR);
    $stmt->bindParam(':step', $step, PDO::PARAM_STR);
    $stmt->execute();
    return true;
  }

  public function get_user($chat_id) {
    if (!$this->user_exists($chat_id)) return false;

    $hash_id = md5($chat_id);

    $stmt = $this->connection->prepare("SELECT * FROM `users` WHERE `id` = :id");
    $stmt->bindParam(':id', $hash_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user;
  }
  
  public function add_question($type, $question) {
    $stmt = $this->connection->prepare("INSERT INTO `questions` (`type`, `question`) VALUES (:type, :question)");
    $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    $stmt->bindParam(':question', $question, PDO::PARAM_STR);
    $stmt->execute();
    return true;
  }
  
  public function random_question($type) {
    $stmt = $this->connection->prepare("SELECT * FROM `questions` WHERE `type` = '$type' ORDER BY RANDOM() LIMIT 1;");
    $stmt->execute();
    
    while ($row = $stmt->fetch()) return base64_decode($row['question']);
  }

  public function change_user($chat_id, $key, $value) {
    $hash_id = md5($chat_id);
    return $this->connection->query("UPDATE `users` SET `$key` = '$value' WHERE `id` = '$hash_id';");
  }
}