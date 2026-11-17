<?php

namespace ADP\BaseVersion\Includes\Database\Repository;

use ADP\BaseVersion\Includes\Database\Models\ChatMessage;

class ChatRepository {
    /**
     * @param ChatMessage $message
     *
     * @return int inserted message id
     */
    public static function addMessage($message)
    {
        global $wpdb;

        $table = $wpdb->prefix . ChatMessage::TABLE_NAME;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, $message->getDataForDB());

        return $wpdb->insert_id;
    }

    /**
     * @param int      $userId
     * @param int      $limit
     * @param int|null $ruleId when given, only messages tied to THIS rule (or to no rule at
     *                         all - general conversation) are returned, excluding messages
     *                         tied to a DIFFERENT rule the user was discussing earlier -
     *                         keeps the AI's context focused on the rule actually open now
     *                         instead of accumulating unrelated history as the admin clicks
     *                         through different rules, which both bloats the prompt and
     *                         risks the model conflating one rule's settings with another's
     *
     * @return array<int, ChatMessage> oldest first
     */
    public static function getHistory($userId, $limit = 100, $ruleId = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . ChatMessage::TABLE_NAME;

        if ($ruleId !== null) {
            $sql  = "SELECT * FROM (
                SELECT * FROM $table WHERE user_id = %d AND (rule_id = %d OR rule_id IS NULL) ORDER BY id DESC LIMIT %d
            ) as recent ORDER BY id ASC";
            $args = array($userId, $ruleId, $limit);
        } else {
            $sql  = "SELECT * FROM (
                SELECT * FROM $table WHERE user_id = %d ORDER BY id DESC LIMIT %d
            ) as recent ORDER BY id ASC";
            $args = array($userId, $limit);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);

        return array_map(function ($row) {
            return ChatMessage::fromArray($row);
        }, $rows);
    }

    public static function clearHistory($userId)
    {
        global $wpdb;

        $table = $wpdb->prefix . ChatMessage::TABLE_NAME;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE user_id = %d", $userId));
    }
}
