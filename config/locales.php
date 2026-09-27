<?php

/*
 * Languages the customer-facing side speaks. The first one is the base
 * language: it lives in the regular columns and is what staff work in. The
 * others are optional translations that fall back to it when left empty.
 *
 * Adding a language: add it here, add lang/{code}/*.php, done. The admin
 * forms grow a tab for it automatically.
 */
return [
    'available' => [
        'nl' => 'Nederlands',
        'fr' => 'Français',
    ],
];
