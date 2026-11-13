<?php

// ---------------------------------------------------------------------------
// The questions of the agent shop. The customer answers them after the payment
// and the answers are sent to the administrator with the details.
// ---------------------------------------------------------------------------
$questions = nexora_agent_shop_questions('agentshop');
$questionsText = implode("\n", array_map(fn($q) => (string) ($q['question'] ?? ''), $questions));
