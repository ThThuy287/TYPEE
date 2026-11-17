<?php

namespace ADP\BaseVersion\Includes\Database\Models;

class ChatMessage {
    const TABLE_NAME = 'wdp_ai_chat_messages';

    const ROLE_USER = 'user';
    const ROLE_ASSISTANT = 'assistant';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $user_id;

    /**
     * @var string
     */
    public $role;

    /**
     * @var string
     */
    public $message;

    /**
     * @var int|null id of the rule open in the editor when this message was sent
     */
    public $rule_id;

    /**
     * @var string
     */
    public $created_at;

    public function __construct($id, $user_id, $role, $message, $rule_id, $created_at)
    {
        $this->id = intval($id);
        $this->user_id = intval($user_id);
        $this->role = $role === self::ROLE_ASSISTANT ? self::ROLE_ASSISTANT : self::ROLE_USER;
        $this->message = strval($message);
        $this->rule_id = $rule_id === null || $rule_id === '' ? null : intval($rule_id);
        $this->created_at = $created_at ?: current_time('mysql');
    }

    public static function fromArray($data) {
        $message = array(
            'id'         => 0,
            'user_id'    => 0,
            'role'       => self::ROLE_USER,
            'message'    => '',
            'rule_id'    => null,
            'created_at' => null,
        );
        $message = array_merge($message, $data);

        return new self($message['id'], $message['user_id'], $message['role'], $message['message'], $message['rule_id'], $message['created_at']);
    }

    public function getData() {
        return array(
            'id'         => $this->id,
            'role'       => $this->role,
            'message'    => $this->message,
            'rule_id'    => $this->rule_id,
            'created_at' => $this->created_at,
        );
    }

    public function getDataForDB() {
        return array(
            'user_id'    => $this->user_id,
            'role'       => $this->role,
            'message'    => $this->message,
            'rule_id'    => $this->rule_id,
            'created_at' => $this->created_at,
        );
    }

    public static function createTable() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charsetCollate = $wpdb->get_charset_collate();
        $tableName = $wpdb->prefix . self::TABLE_NAME;

        $sql = /** @lang MySQL */
            "CREATE TABLE {$tableName} (
            id INT NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(16) NOT NULL,
            message LONGTEXT NOT NULL,
            rule_id INT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charsetCollate;";

        dbDelta($sql);
    }

    public static function deleteTable() {
        global $wpdb;

        $tableName = $wpdb->prefix . self::TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS $tableName");
    }
}
