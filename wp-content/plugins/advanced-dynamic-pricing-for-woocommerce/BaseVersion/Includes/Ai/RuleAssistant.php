<?php

namespace ADP\BaseVersion\Includes\Ai;

use ADP\BaseVersion\Includes\Database\Models\ChatMessage;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;

defined('ABSPATH') or exit;

/**
 * Natural-language rule drafting for the Free-tier rule shapes: discounts (on a
 * product, category, tag, SKU, custom taxonomy term, or attribute term), bulk
 * (quantity-tiered) discounts, role discounts, role+bulk discounts, cart
 * discounts, gifts, and BOGO (free). Talks to whatever provider is configured
 * under Settings -> Connectors via WordPress core's AI Client
 * (wp_ai_client_prompt(), WP 7.0+).
 *
 * This class is only the conversation: the prompt, the history, the reply. The rule itself
 * is built by RuleBuilder from the fields the model extracted.
 *
 * Deliberately never writes to the database itself: the AI is not trusted to
 * commit pricing changes unsupervised. It only builds the rule's data and hands
 * it to the admin JS to insert as an unsaved row - the admin reviews it and
 * clicks the rule's own Save button, going through the exact same
 * ajax_save_rule() path a manually-built rule would.
 */
class RuleAssistant
{
    const LOG_FILE = 'adp-ai-chat-debug.log';

    // how many prior messages (user + assistant turns combined) to replay as
    // conversation history on every request - the AI Client is stateless per
    // request, so without this the model never sees anything before the
    // current message and can't handle follow-ups ("make it 20% instead")
    const HISTORY_LIMIT = 20;

    const AVAILABILITY_TRANSIENT = 'adp_ai_chat_availability';

    const TAB_RULES = 'rules';

    const MODE_RULES = 'rules';
    const MODE_SETTINGS = 'settings';

    /**
     * Check of whether the chat can be used at all right now, for the admin page to decide
     * what to show BEFORE the admin types anything - handle() already checks
     * wp_ai_client_prompt() existence and is_supported_for_text_generation() per request, but
     * that only surfaces as a reply after a message is sent; this lets the UI show the right
     * state (and hide the input entirely) up front instead.
     *
     * is_supported_for_text_generation() is NOT a free local check for every provider: for a
     * provider whose model list isn't baked in (e.g. the DeepSeek connector), it lists models
     * via a real HTTP request to the provider's API, and WordPress core's AI client has no
     * persistent cache wired up for that by default - only an in-memory one scoped to the
     * current request. Without caching our own result, that network round trip (seconds, and
     * occasionally failing outright) would run again on every single admin page load of the
     * Rules tab. So the outcome is cached here in a short-lived transient, and the check
     * itself is wrapped in a try/catch - a transient provider-side hiccup should not break
     * rendering of the whole admin page.
     *
     * @return array{wp_version_ok: bool, ai_configured: bool}
     */
    public static function checkAvailability()
    {
        global $wp_version;

        $wpVersionOk = $wp_version && version_compare($wp_version, '7.0', '>=');

        $aiConfigured = false;
        if ($wpVersionOk && function_exists('wp_ai_client_prompt')) {
            $cached = get_transient(self::AVAILABILITY_TRANSIENT);
            if ($cached === '1' || $cached === '0') {
                $aiConfigured = $cached === '1';
            } else {
                try {
                    $aiConfigured = wp_ai_client_prompt('ping')->is_supported_for_text_generation();
                } catch (\Throwable $e) {
                    $aiConfigured = false;
                }
                // a failed check is often a passing hiccup of the provider's API (its model list
                // could not be fetched): keep it only briefly, so the chat does not stay hidden
                set_transient(self::AVAILABILITY_TRANSIENT, $aiConfigured ? '1' : '0', $aiConfigured ? 5 * MINUTE_IN_SECONDS : 30);
            }
        }

        return array('wp_version_ok' => $wpVersionOk, 'ai_configured' => $aiConfigured);
    }

    /**
     * @param string        $text          user's chat message
     * @param int|null      $ruleId        rule currently open in the editor, if any (for context only)
     * @param ChatMessage[] $history       prior messages in the conversation, oldest first (e.g. from
     *                                     ChatRepository::getHistory()) - NOT including the current $text
     * @param array|null    $liveRuleState the open rule's current, possibly-unsaved on-screen form state
     *                                     (Rule::getData() shape, from Ajax::parseLiveRuleState()) - preferred
     *                                     over the saved DB copy when given, since the admin may have made
     *                                     (still-unsaved) edits - via this chat or by hand - since the rule
     *                                     was last saved, and the AI should reason about what's actually on
     *                                     screen, not stale data
     * @param string        $tab           key of the admin tab the chat was used on ("rules", "options",
     *                                     "tools", ...). Decides which instruction set the model gets: the
     *                                     rule-drafting one on the Rules tab, the settings/navigation
     *                                     reference everywhere else - a deterministic signal, not something
     *                                     the model is asked to judge up front.
     *
     * @return array{reply: string, rule: array|null, go_to_rules: bool} reply to show in the chat, the
     *                                     created rule's data (Rule::getData() shape) when an action
     *                                     created one, and whether to offer a link to the Rules tab
     */
    public static function handle($text, $ruleId, $history = array(), $liveRuleState = null, $tab = self::TAB_RULES)
    {
        if ( ! function_exists('wp_ai_client_prompt')) {
            return self::result(__('AI Client is not available on this site (requires WordPress 7.0+).', 'advanced-dynamic-pricing-for-woocommerce'));
        }

        if ($tab !== self::TAB_RULES) {
            return self::answerSettingsQuestion($text, $history, true);
        }

        $openRule = $liveRuleState ?: ($ruleId ? RuleDescriber::loadRule($ruleId) : null);
        $ruleContextInstruction = self::buildRuleContextInstruction($openRule, $ruleId);

        // Single pass, always with the FULL instructions (free + Pro mechanisms together) - the
        // model picks whichever action/fields genuinely fit the request, Pro-only ones included,
        // without trying to judge licensing itself (an earlier two-pass design that asked it to
        // flag `needs_pro` up front led to it inventing free-tier workarounds instead of the
        // correct Pro mechanism in some cases, since pass 1 only ever saw the free vocabulary).
        // Licensing is instead checked AFTER the rule is built, below - see detectProFeature().
        $run = self::runPrompt($text, self::getInstructions(self::MODE_RULES) . $ruleContextInstruction, $history);
        if (isset($run['error'])) {
            return self::result($run['error']);
        }
        $decoded = $run['decoded'];

        $reply = (string)$decoded['reply'];
        $existingRule = $openRule && ! empty($decoded['edit_current_rule']) ? $openRule : null;
        $actionName = $decoded['action'] ?? 'none';

        if ($actionName === 'need_settings') {
            // a settings/navigation question asked on the Rules tab - the reference for those
            // isn't part of the rule-drafting instructions, so ask again with it loaded. A
            // wrong call here is harmless (worst case: "I don't know of such a setting"), unlike
            // routing rule drafting through a model-made guess.
            return self::answerSettingsQuestion($text, $history, false);
        }
        $action = RuleBuilder::applyAction($actionName, $decoded, $existingRule);

        if ($action) {
            $proFeature = ProFeatureDetector::detectProFeature($action['rule'], $decoded, $actionName);
            if ($proFeature && ! ProFeatureDetector::isProActive()) {
                // The model built a real rule using a Pro-only mechanism, but this site doesn't
                // actually have Pro - discard the draft entirely and say so, rather than
                // showing something the admin could never save/use. This is the one
                // programmatic (not model-judged) licensing check in the whole flow.
                return self::result(sprintf(
                    /* translators: %s: short name of the Pro-only feature the draft would have needed, e.g. "a Product set deal" */
                    __('That needs %s, which requires the Pro version of this plugin. Upgrading would unlock it.', 'advanced-dynamic-pricing-for-woocommerce'),
                    $proFeature
                ));
            }
        }

        if ($action && ! $action['found']) {
            // Only append the backend's own note when something it alone can verify
            // (a product/category/role/attribute/etc. lookup) failed - that's the one
            // thing the AI has no way to know about. On a clean match the AI's own
            // reply (per the instructions) already confirms the draft in the user's
            // own language, and the new row is right there in the list.
            $reply .= "\n\n" . $action['note'];
        } elseif ( ! $action && $actionName !== 'none') {
            // The AI picked a real action and its `reply` text almost certainly claims a
            // draft was added/updated (per the instructions) - but the builder for that
            // action couldn't produce anything at all (a required field was missing, or a
            // value like `filter_type` fell outside what's actually implemented). Showing
            // the AI's own reply as-is here would tell the admin a draft was added when
            // nothing was - so it's replaced with an honest fallback instead.
            $reply = __("Sorry, I couldn't put together a draft for that - could you rephrase or give a bit more detail?", 'advanced-dynamic-pricing-for-woocommerce');
        }

        return self::result(
            $reply,
            $action ? $action['rule'] : null,
            $action ? $action['titles'] : null,
            $action ? $action['links'] : null,
            $action ? $action['edited'] : false
        );
    }

    private static function result($reply, $rule = null, $titles = null, $links = null, $edited = false)
    {
        return array(
            'reply'       => $reply,
            'rule'        => $rule,
            'titles'      => $titles,
            'links'       => $links,
            'edited'      => $edited,
            'go_to_rules' => false,
        );
    }

    /**
     * Answers from the settings/navigation reference (instructions/settings-*.md) - never
     * drafts a rule. `go_to_rules` is how that instruction set says "this is a request to
     * make/change a rule": off the Rules tab the frontend then adds a link to that tab under
     * the reply ($offerRulesTab). On the Rules tab itself that answer makes no sense (the
     * rule-drafting pass just sent the question here), so it becomes a "please rephrase".
     */
    private static function answerSettingsQuestion($text, $history, $offerRulesTab)
    {
        $run = self::runPrompt($text, self::getInstructions(self::MODE_SETTINGS), $history, true);
        if (isset($run['error'])) {
            return self::result($run['error']);
        }

        $wantsRule = ($run['decoded']['action'] ?? 'none') === 'go_to_rules';
        if ($wantsRule && ! $offerRulesTab) {
            return self::result(self::unableToCreateMessage());
        }

        $result = self::result((string)$run['decoded']['reply']);
        $result['go_to_rules'] = $wantsRule;

        return $result;
    }

    /**
     * Shown when no rule could be made from the request; the chat turns these two links
     * (and only links to our docs/support) into real links.
     */
    private static function unableToCreateMessage()
    {
        return sprintf(
            /* translators: 1: documentation URL, 2: support URL */
            __('Unable to create the rule. Please consult the <a href="%1$s">documentation</a> or contact <a href="%2$s">support</a>, providing the exact prompt for which the rule could not be created.', 'advanced-dynamic-pricing-for-woocommerce'),
            'https://docs.algolplus.com/category/frequently-created-rules/',
            'https://algolplus.freshdesk.com/support/tickets/new'
        );
    }

    /**
     * The "admin currently has rule X open..." suffix appended to the system instruction -
     * split out into its own method mainly for readability.
     */
    private static function buildRuleContextInstruction($openRule, $ruleId)
    {
        if ($openRule) {
            // $ruleId is empty for a rule the chat itself just drafted and the admin hasn't
            // clicked Save on yet - still a real, currently-open rule as far as editing goes,
            // just without a database id to quote
            $ruleLabel = $ruleId
                ? sprintf('#%d ("%s")', $ruleId, $openRule['title'])
                : sprintf('an unsaved draft titled "%s"', $openRule['title']);

            // the editing rules themselves are in instructions/00-overview.md ("Editing the rule
            // that's currently open") - only what is specific to this request goes here
            return sprintf(
                "\n\nThe admin currently has %s open in the rule editor. Its relevant settings, " .
                'exactly as currently shown on screen right now (which may include edits - from this chat or ' .
                "made by hand - that haven't been saved yet, so this can differ from what's in the database), " .
                "are: %s\n\n" .
                'If the user asks what this rule currently does, answer from the settings shown above. If the ' .
                'message describes a change to THIS rule - including a correction of a draft you made a moment ' .
                'ago - follow "Editing the rule that\'s currently open"; the values to keep in a list field are ' .
                'the ones shown above.',
                $ruleLabel,
                RuleDescriber::summarizeRuleForContext($openRule)
            );
        }

        if ($ruleId) {
            return "\n\nThe admin currently has rule #{$ruleId} open in the rule editor.";
        }

        return '';
    }

    /**
     * Sends one prompt to the configured AI provider and returns either
     * `{'decoded': array}` (parsed JSON reply) or `{'error': string}` (a user-facing message
     * for handle() to return immediately).
     *
     * @param bool $acceptPlainText when the model answers with plain text instead of the JSON
     *                              object it was asked for, use that text as the reply
     *                              (`action: none`) rather than failing. Only safe where the
     *                              answer is nothing but text anyway (settings questions) - seen
     *                              in practice there, since the conversation history it is shown
     *                              consists of earlier replies stored as plain text.
     */
    private static function runPrompt($text, $systemInstruction, $history, $acceptPlainText = false)
    {
        self::log('REQUEST', array(
            'message'      => $text,
            'history'      => array_map(function (ChatMessage $m) {
                return array('role' => $m->role, 'message' => $m->message);
            }, $history),
            'instructions' => $systemInstruction,
        ));

        // Not using as_json_response() here: the configured connector (DeepSeek)
        // reports is_supported_for_text_generation() === false as soon as a JSON
        // schema is attached, i.e. it doesn't support schema-constrained output.
        // Falling back to plain text generation with strict prompt instructions
        // and lenient parsing below instead.
        $historyMessages = self::buildHistoryMessages($history);
        $buildPrompt     = function ($options) use ($text, $systemInstruction, $historyMessages) {
            $prompt = wp_ai_client_prompt($text)->using_system_instruction($systemInstruction);
            if ($options['temperature'] !== null) {
                $prompt = $prompt->using_temperature($options['temperature']);
            }
            if ($options['max_tokens'] !== null) {
                $prompt = $prompt->using_max_tokens($options['max_tokens']);
            }

            return $historyMessages ? $prompt->with_history(...$historyMessages) : $prompt;
        };

        // temperature / max tokens as set in Settings -> AI Assistant, minus what this model does not
        // take (by its metadata, or because it already refused it once - see ModelSettings)
        $options = ModelSettings::forPrompt();
        $prompt  = $buildPrompt($options);

        // No is_supported_for_text_generation() check here - it's a real (uncached) API call
        // to the provider (see checkAvailability()'s doc comment), and the admin page already
        // ran that check once at load time and hides the compose area entirely when it fails,
        // so this code only runs when a provider is expected to be available. generate_text()
        // below still fails gracefully (is_wp_error() branch) if that turns out to be wrong.
        $result = $prompt->generate_text_result();

        // the model refused an option its metadata claims it takes: remember that, so it is
        // never sent to this model again, and ask once more without it
        while (is_wp_error($result) && ($refused = ModelSettings::refusedOption($result->get_error_message()))
            && $options[$refused] !== null) {
            self::log('RESPONSE', array('wp_error' => $result->get_error_message(), 'retrying' => 'without ' . $refused));
            ModelSettings::remember($refused);
            $options[$refused] = null;
            $prompt            = $buildPrompt($options);
            $result            = $prompt->generate_text_result();
        }

        if (is_wp_error($result)) {
            // one retry: the provider layer re-fetches its model list on every request, and a
            // single hiccup there surfaces as an error even though the provider is fine
            self::log('RESPONSE', array('wp_error' => $result->get_error_message(), 'retrying' => true));
            $result = $prompt->generate_text_result();
        }

        if (is_wp_error($result)) {
            self::log('RESPONSE', array('wp_error' => $result->get_error_message()));

            if (stripos($result->get_error_message(), 'No models found') !== false) {
                // the provider's model list could not be loaded - usually its API being briefly
                // unavailable or rate limited, not a missing connector
                return array('error' => __('The AI provider is not responding right now (its list of models could not be loaded). Please try again in a minute.', 'advanced-dynamic-pricing-for-woocommerce'));
            }

            return array('error' => sprintf(
                /* translators: %s: error message from the AI provider */
                __('AI request failed: %s', 'advanced-dynamic-pricing-for-woocommerce'),
                $result->get_error_message()
            ));
        }

        ModelSettings::usedModel($result->getProviderMetadata()->getId() . '/' . $result->getModelMetadata()->getId());
        try {
            $result = $result->toText();
        } catch (\Throwable $e) {
            $result = '';
        }

        self::log('RESPONSE', array('raw' => $result));

        if (trim((string)$result) === '') {
            // Seen with DeepSeek specifically: a low max_tokens budget can get fully
            // spent on hidden reasoning tokens before any visible output is written,
            // leaving the response empty. Bumping using_max_tokens() above is the
            // main mitigation; this is the user-facing fallback for when it still happens.
            return array('error' => __('The AI returned an empty response - please try again.', 'advanced-dynamic-pricing-for-woocommerce'));
        }

        $decoded = self::parseJsonReply($result);

        if (( ! is_array($decoded) || empty($decoded['reply'])) && $acceptPlainText && strpos(ltrim((string)$result), '{') !== 0) {
            return array('decoded' => array('reply' => trim((string)$result), 'action' => 'none'));
        }

        if (is_array($decoded) && ($decoded['action'] ?? '') === 'need_settings') {
            // a pure hand-off - its `reply` is never shown, so an empty one is fine
            return array('decoded' => array('reply' => '', 'action' => 'need_settings'));
        }

        if ( ! is_array($decoded) || empty($decoded['reply'])) {
            // model didn't follow the "JSON only" instruction - the raw text is logged
            // above for debugging, but showing it as-is to the user is just confusing
            return array('error' => self::unableToCreateMessage());
        }

        return array('decoded' => $decoded);
    }

    /**
     * Converts stored chat turns into the AI Client's Message DTOs for `with_history()`.
     * Order is preserved (oldest first), matching what `with_history()` expects - it
     * prepends them, in the given order, right before the current message being built.
     *
     * @param ChatMessage[] $history
     *
     * @return (UserMessage|ModelMessage)[]
     */
    private static function buildHistoryMessages($history)
    {
        $messages = array();

        foreach ($history as $chatMessage) {
            if ( ! $chatMessage instanceof ChatMessage || trim($chatMessage->message) === '') {
                continue;
            }

            $part = new MessagePart($chatMessage->message);
            $messages[] = $chatMessage->role === ChatMessage::ROLE_ASSISTANT
                ? new ModelMessage(array($part))
                : new UserMessage(array($part));
        }

        return $messages;
    }

    /**
     * Two instruction sets, picked by the caller (see handle()), never by the model:
     *
     * - MODE_RULES: everything needed to draft rules (NN-*.md) - each section documents its
     *   free and Pro-only mechanisms TOGETHER, regardless of whether Pro is active. An earlier
     *   two-pass design (free-only first, Pro detail on a second request) led the model to
     *   invent incorrect free-tier workarounds for requests that needed a Pro-only mechanism;
     *   licensing is instead enforced programmatically after the rule is built, see
     *   detectProFeature().
     * - MODE_SETTINGS: the settings/tabs reference (settings-*.md), for questions only.
     *
     * Each set is a stable prefix of the request, which is what the provider's prompt cache
     * keys on - keep anything request-specific (the open rule) appended AFTER it.
     */
    private static function getInstructions($mode)
    {
        static $instructions = array();

        if ( ! isset($instructions[$mode])) {
            $instructions[$mode] = implode("\n\n---\n\n", self::instructionSections($mode));
        }

        return $instructions[$mode];
    }

    /**
     * The rule-drafting instructions section by section, for a caller that hands them to a
     * model of its own (see Abilities::getGuide()).
     *
     * @return array<string, string> file name without extension => its text
     */
    public static function ruleInstructionSections()
    {
        return self::instructionSections(self::MODE_RULES);
    }

    private static function instructionSections($mode)
    {
        $files = glob(__DIR__ . '/instructions' . ($mode === self::MODE_SETTINGS ? '/settings-*.md' : '/[0-9][0-9]-*.md'));
        sort($files);

        $sections = array();
        foreach ($files as $file) {
            //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $sections[basename($file, '.md')] = file_get_contents($file);
        }

        return $sections;
    }

    /**
     * The model is instructed (see instructions/*.md) to reply with nothing but a
     * JSON object, but models routinely wrap it in a ```json fence or add a stray
     * sentence anyway - strip that before decoding.
     *
     * @return array|null
     */
    private static function parseJsonReply($text)
    {
        $text = trim($text);

        if (strpos($text, '```') !== false) {
            $text = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text);
            $text = trim($text);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // last resort: grab the outermost {...} in case there's leading/trailing prose
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Debug log of every AI request/response, timestamped. WP_DEBUG-gated since
     * it can contain full chat text. File lives in uploads/, not the plugin
     * folder, so it survives plugin updates and isn't web-served as PHP.
     */
    private static function log($label, $data)
    {
        if ( ! defined('WP_DEBUG') || ! WP_DEBUG) {
            return;
        }

        $uploadDir = wp_upload_dir();
        if (empty($uploadDir['basedir'])) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s\n",
            current_time('mysql'),
            $label,
            wp_json_encode($data)
        );

        //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        file_put_contents(
            trailingslashit($uploadDir['basedir']) . self::LOG_FILE,
            $line,
            FILE_APPEND | LOCK_EX
        );
    }
}
