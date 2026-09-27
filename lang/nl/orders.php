<?php

return [
    'status' => [
        'new' => 'Nieuw',
        'confirmed' => 'Bevestigd',
        'delivered' => 'Geleverd',
        'cancelled' => 'Geannuleerd',
    ],
    'fields' => [
        'number' => 'Bestelnummer',
        'customer' => 'Klant',
        'placed_by' => 'Geplaatst door',
        'requested_delivery_date' => 'Gewenste leverdatum',
        'address' => 'Adres',
        'product' => 'Product',
        'quantity' => 'Hoeveelheid',
        'unit_price' => 'Prijs',
        'line_total' => 'Totaal',
        'subtotal' => 'Subtotaal excl. btw',
        'vat' => 'Btw',
        'total' => 'Totaal incl. btw',
    ],
    'day_price' => 'Dagprijs',
    'unpriced_notice' => 'Deze bestelling bevat producten aan dagprijs. Het totaal wordt aangevuld bij bevestiging.',
    'errors' => [
        'empty' => 'Kies minstens één product.',
        'unavailable' => 'Dit product is niet meer beschikbaar in jouw prijslijst.',
        'whole' => ':product kan enkel per stuk besteld worden.',
        'minimum' => 'Van :product bestel je minstens :min.',
    ],
    'mail' => [
        'received' => [
            'subject' => 'Nieuwe bestelling :number — :customer',
            'heading' => 'Nieuwe bestelling :number',
            'action' => 'Openen in het beheer',
        ],
        'confirmation' => [
            'subject' => 'We hebben je bestelling :number goed ontvangen',
            'heading' => 'Bedankt voor je bestelling',
            'intro' => 'We hebben bestelling :number goed ontvangen en nemen ze zo snel mogelijk in behandeling.',
            'action' => 'Bestelling bekijken',
        ],
    ],
];
