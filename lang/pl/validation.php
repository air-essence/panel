<?php

// Polskie komunikaty walidacji dla reguł używanych w panelu. Brakujące reguły spadają do lang/en.
return [
    'required' => 'Pole :attribute jest wymagane.',
    'string' => 'Pole :attribute musi być tekstem.',
    'email' => 'Pole :attribute musi być poprawnym adresem e-mail.',
    'integer' => 'Pole :attribute musi być liczbą całkowitą.',
    'exists' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'unique' => 'Taka wartość pola :attribute już istnieje.',
    'in' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'enum' => 'Wybrana wartość pola :attribute jest nieprawidłowa.',
    'max' => ['string' => 'Pole :attribute może mieć najwyżej :max znaków.', 'numeric' => 'Pole :attribute nie może być większe niż :max.'],
    'min' => ['string' => 'Pole :attribute musi mieć co najmniej :min znaków.', 'numeric' => 'Pole :attribute musi być co najmniej :min.'],

    'attributes' => [
        'name' => 'nazwa',
        'short_name' => 'skrót',
        'email' => 'e-mail',
        'password' => 'hasło',
        'type' => 'typ',
        'company_id' => 'spółka',
        'baselinker_source' => 'źródło w BaseLinkerze',
        'group' => 'grupa',
        'baselinker_status_id' => 'ID w BaseLinkerze',
        'notes' => 'uwagi',
        'role' => 'rola',
    ],
];
