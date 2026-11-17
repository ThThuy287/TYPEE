<?php

namespace ADP\BaseVersion\Includes\Ai;

defined('ABSPATH') or exit;

/**
 * Which generation options the assistant sends (temperature, max tokens) to the model the AI
 * Client picks, and what it has learned about that model.
 *
 * Settings -> AI Assistant has a value for each option: "auto", "off" (never sent) or a number. An
 * option is not sent either when the model's metadata says it does not take it, or when the
 * model already refused it once - metadata is not always right (a provider plugin may not know
 * a newer model yet), so a refusal is remembered and that option is not sent to the model again.
 * Everything learned is forgotten when a connector is added, changed or removed.
 */
class ModelSettings
{
    const STATE_OPTION = 'adp_ai_model_state';

    const DEFAULT_TEMPERATURE = 0.2;
    const DEFAULT_MAX_TOKENS  = 2500;

    public static function init()
    {
        add_action('added_option', array(__CLASS__, 'onOptionChanged'));
        add_action('updated_option', array(__CLASS__, 'onOptionChanged'));
        add_action('deleted_option', array(__CLASS__, 'onOptionChanged'));
    }

    /**
     * A connector is set up by its API key option ("connectors_ai_openai_api_key"): a change
     * of one may mean another provider and model, so what was learned no longer applies.
     */
    public static function onOptionChanged($option)
    {
        if (is_string($option) && preg_match('/^connectors_ai_.+_api_key$/', $option)) {
            delete_option(self::STATE_OPTION);
            delete_transient(RuleAssistant::AVAILABILITY_TRANSIENT);
        }
    }

    /**
     * @return array{model: string|null, temperature: float|null, max_tokens: int|null} what to
     *     send to the model the prompt will go to; null = do not send that option
     */
    public static function forPrompt()
    {
        $state = self::state();
        if ( ! isset($state['model'])) {
            $state = array_merge($state, self::resolveModel());
            self::saveState($state);
        }

        $temperature = self::setting('ai_assistant_temperature');
        $maxTokens   = self::setting('ai_assistant_max_tokens');

        return array(
            'model'       => $state['model'],
            'temperature' => $temperature === 'off' || ! empty($state['no_temperature'])
                ? null
                : ($temperature === 'auto' ? self::DEFAULT_TEMPERATURE : (float)$temperature),
            'max_tokens'  => $maxTokens === 'off' || ! empty($state['no_max_tokens'])
                ? null
                : ($maxTokens === 'auto' ? self::DEFAULT_MAX_TOKENS : (int)$maxTokens),
        );
    }

    /**
     * The provider refused an option: remember it for this model.
     *
     * @param string $option 'temperature' or 'max_tokens'
     */
    public static function remember($option)
    {
        $state                  = self::state();
        $state['no_' . $option] = true;
        self::saveState($state);
    }

    /**
     * Which option an error message from the provider complains about, if any.
     *
     * @return string|null 'temperature', 'max_tokens' or null
     */
    public static function refusedOption($errorMessage)
    {
        if (stripos($errorMessage, 'temperature') !== false) {
            return 'temperature';
        }
        if (preg_match('/max[_ ]?(completion[_ ]?)?tokens/i', $errorMessage)) {
            return 'max_tokens';
        }

        return null;
    }

    /**
     * The model that actually answered: if it is not the one expected, what was learned was
     * about another model.
     */
    public static function usedModel($model)
    {
        $state = self::state();
        if (isset($state['model']) && $state['model'] === $model) {
            return;
        }
        // the model could not be told in advance: what was learned in this request was about it
        if (empty($state['model'])) {
            $state['model'] = $model;
            self::saveState($state);

            return;
        }
        self::saveState(array('model' => $model));
    }

    /**
     * For the settings page: the model and what is not sent to it.
     *
     * @return array{model: string|null, no_temperature: bool, no_max_tokens: bool}
     */
    public static function describe()
    {
        $state = self::state();

        return array(
            'model'          => $state['model'] ?? null,
            'no_temperature' => ! empty($state['no_temperature']),
            'no_max_tokens'  => ! empty($state['no_max_tokens']),
        );
    }

    /**
     * The model the AI Client will pick for a text prompt - the first one, across the configured
     * providers, that can generate text, as the AI Client's own selection does - and what its
     * metadata says about the two options.
     */
    private static function resolveModel()
    {
        $resolved = array('model' => null, 'no_temperature' => false, 'no_max_tokens' => false);
        if ( ! class_exists('\WordPress\AiClient\AiClient')) {
            return $resolved;
        }

        try {
            $config = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
            $config->setSystemInstruction('-');
            $message = new \WordPress\AiClient\Messages\DTO\UserMessage(array(
                new \WordPress\AiClient\Messages\DTO\MessagePart('-'),
            ));
            $requirements = \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
                \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
                array($message),
                $config
            );
            $found = \WordPress\AiClient\AiClient::defaultRegistry()->findModelsMetadataForSupport($requirements);
        } catch (\Throwable $e) {
            return $resolved;
        }

        foreach ($found as $providerModels) {
            foreach ($providerModels->getModels() as $model) {
                $options = array();
                foreach ($model->getSupportedOptions() as $option) {
                    $options[] = $option->getName()->value;
                }
                $resolved['model']          = $providerModels->getProvider()->getId() . '/' . $model->getId();
                $resolved['no_temperature'] = ! in_array('temperature', $options, true);
                $resolved['no_max_tokens']  = ! in_array('maxTokens', $options, true);

                return $resolved;
            }
        }

        return $resolved;
    }

    private static function setting($name)
    {
        $value = (string)adp_context()->getOption($name, 'auto');

        return $value === '' ? 'auto' : $value;
    }

    private static function state()
    {
        $state = get_option(self::STATE_OPTION, array());

        return is_array($state) ? $state : array();
    }

    private static function saveState($state)
    {
        update_option(self::STATE_OPTION, $state, false);
    }
}
