<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CRM Assistant
    |--------------------------------------------------------------------------
    |
    | The assistant answers questions by calling read-only tools against the
    | CRM. It is disabled by default: turning it on sends record data to a
    | third party, which is a decision an operator should make deliberately.
    |
    */

    'enabled' => env('AI_ASSISTANT_ENABLED', false),

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),

        // gpt-5.6-terra balances capability and cost; gpt-5.6-luna is the
        // cheap option for high volume, gpt-5.6-sol the most capable.
        'model' => env('OPENAI_MODEL', 'gpt-5.6-terra'),

        'timeout' => (int) env('OPENAI_TIMEOUT', 60),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 1500),
    ],

    /*
    | How many times the model may call tools before we force a final answer.
    | Each round trip is a paid request, so this is a cost ceiling as much as a
    | safety one.
    */
    'max_tool_iterations' => (int) env('AI_MAX_TOOL_ITERATIONS', 5),

    /* Rows any single tool call may return, to bound tokens and exposure. */
    'max_rows_per_tool' => (int) env('AI_MAX_ROWS_PER_TOOL', 15),

    /* Turns of history replayed to the model on each request. */
    'history_limit' => (int) env('AI_HISTORY_LIMIT', 20),

    /*
    | Mask email addresses and phone numbers in tool output. The assistant can
    | still find and describe a contact; it just never receives the raw
    | identifiers. Leave on unless your DPA with the provider covers PII.
    */
    'redact_pii' => env('AI_REDACT_PII', true),

];
