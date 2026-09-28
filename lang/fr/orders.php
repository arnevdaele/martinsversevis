<?php

return [
    'status' => [
        'new' => 'Nouvelle',
        'confirmed' => 'Confirmée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ],
    'fields' => [
        'number' => 'Numéro de commande',
        'customer' => 'Client',
        'placed_by' => 'Passée par',
        'requested_delivery_date' => 'Date de livraison souhaitée',
        'address' => 'Adresse',
        'product' => 'Produit',
        'quantity' => 'Quantité',
        'unit_price' => 'Prix',
        'line_total' => 'Total',
        'subtotal' => 'Sous-total HTVA',
        'vat' => 'TVA',
        'total' => 'Total TVAC',
    ],
    'day_price' => 'Prix du jour',
    'unpriced_notice' => 'Cette commande contient des produits au prix du jour. Le total sera complété lors de la confirmation.',
    'errors' => [
        'empty' => 'Choisissez au moins un produit.',
        'unavailable' => 'Ce produit n’est plus disponible dans votre liste de prix.',
        'whole' => ':product ne peut être commandé qu’à la pièce.',
        'minimum' => 'Pour :product, commandez au moins :min.',
    ],
    'mail' => [
        'received' => [
            'subject' => 'Nouvelle commande :number — :customer',
            'heading' => 'Nouvelle commande :number',
            'action' => 'Ouvrir dans la gestion',
        ],
        'confirmation' => [
            'subject' => 'Nous avons bien reçu votre commande :number',
            'heading' => 'Merci pour votre commande',
            'intro' => 'Nous avons bien reçu la commande :number et la traiterons dans les meilleurs délais.',
            'action' => 'Voir la commande',
        ],
        'confirmed' => [
            'subject' => 'Votre commande :number est confirmée',
            'heading' => 'Votre commande est confirmée',
            'intro' => 'Nous avons confirmé la commande :number. Vous trouverez ci-dessous les lignes et les prix définitifs.',
        ],
        'cancelled' => [
            'subject' => 'Votre commande :number est annulée',
            'heading' => 'Votre commande est annulée',
            'intro' => 'La commande :number est annulée et ne sera pas livrée. Une question ? N’hésitez pas à répondre à cet e-mail.',
        ],
    ],
];
