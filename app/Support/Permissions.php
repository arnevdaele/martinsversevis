<?php

namespace App\Support;

/**
 * The single list of what can be granted. `php artisan permissions:sync`
 * writes it to the database; the role form in the admin is generated from it,
 * so adding an entry here is all it takes to make a new permission assignable.
 */
final class Permissions
{
    public const SUPER_ADMIN_ROLE = 'Super admin';

    public const GUARD = 'web';

    /** @return array<string, array{label: string, abilities: array<string, string>}> */
    public static function groups(): array
    {
        $crud = fn (string $noun) => [
            'view' => "{$noun} bekijken",
            'create' => "{$noun} aanmaken",
            'update' => "{$noun} bewerken",
            'delete' => "{$noun} verwijderen",
        ];

        return [
            'customers' => [
                'label' => 'Klanten',
                'abilities' => $crud('Klanten') + [
                    'manage-logins' => 'Portaal-logins beheren en uitnodigen',
                ],
            ],
            'customer-types' => [
                'label' => 'Klanttypes',
                'abilities' => $crud('Klanttypes'),
            ],
            'products' => [
                'label' => 'Producten',
                'abilities' => $crud('Producten'),
            ],
            'price-lists' => [
                'label' => 'Prijslijsten',
                'abilities' => $crud('Prijslijsten'),
            ],
            'delivery' => [
                'label' => 'Levering',
                'abilities' => $crud('Leverschema\'s en sluitingen'),
            ],
            'orders' => [
                'label' => 'Bestellingen',
                'abilities' => [
                    'view' => 'Bestellingen bekijken',
                    'update' => 'Bestellingen behandelen (status, notities, prijzen)',
                    'delete' => 'Bestellingen verwijderen',
                    'export' => 'Bestellingen exporteren voor de boekhouding',
                    'receive-notifications' => 'E-mail ontvangen bij nieuwe bestellingen',
                ],
            ],
            'failed-mails' => [
                'label' => 'Mislukte e-mails',
                'abilities' => [
                    'view' => 'Mislukte e-mails bekijken',
                    'retry' => 'Mislukte e-mails opnieuw versturen',
                    'delete' => 'Mislukte e-mails verwijderen',
                ],
            ],
            'settings' => [
                'label' => 'Instellingen',
                'abilities' => [
                    'update' => 'Bedrijfsgegevens bewerken (hoofding van de leveringsbon)',
                ],
            ],
            'users' => [
                'label' => 'Beheerders',
                'abilities' => $crud('Beheerders'),
            ],
            'roles' => [
                'label' => 'Rollen & rechten',
                'abilities' => $crud('Rollen'),
            ],
        ];
    }

    /** @return list<string> e.g. "customers.view" */
    public static function all(): array
    {
        $names = [];

        foreach (self::groups() as $group => $definition) {
            foreach (array_keys($definition['abilities']) as $ability) {
                $names[] = "{$group}.{$ability}";
            }
        }

        return $names;
    }

    /**
     * Roles created on first install. Everything stays editable in the admin
     * except the super admin, who bypasses checks altogether.
     *
     * @return array<string, list<string>> role name => permissions
     */
    public static function defaultRoles(): array
    {
        return [
            self::SUPER_ADMIN_ROLE => [],
            'Beheerder' => array_values(array_filter(
                self::all(),
                fn (string $name) => ! str_starts_with($name, 'users.') && ! str_starts_with($name, 'roles.'),
            )),
            'Verkoop' => [
                'customers.view', 'customers.create', 'customers.update', 'customers.manage-logins',
                'products.view', 'price-lists.view',
                'orders.view', 'orders.update', 'orders.receive-notifications',
            ],
        ];
    }
}
