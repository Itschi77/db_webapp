<?php

return [
    'writes_enabled' => (bool) env('DUNNING_WRITES_ENABLED', false),

    // Betriebliche Standardwerte, ausdrücklich keine gesetzlichen Vorgaben.
    'first_reminder_after_due_days' => (int) env('DUNNING_FIRST_AFTER_DUE_DAYS', 7),
    'second_reminder_after_days' => (int) env('DUNNING_SECOND_AFTER_DAYS', 14),
    'third_reminder_after_days' => (int) env('DUNNING_THIRD_AFTER_DAYS', 14),
    'payment_deadline_days' => (int) env('DUNNING_PAYMENT_DEADLINE_DAYS', 7),

    // Zusätzliche Gebühr je neu gebuchter Mahnstufe. Standard bewusst 0,00 €.
    'fees' => [
        1 => (float) env('DUNNING_FEE_LEVEL_1', 0),
        2 => (float) env('DUNNING_FEE_LEVEL_2', 0),
        3 => (float) env('DUNNING_FEE_LEVEL_3', 0),
    ],
];
