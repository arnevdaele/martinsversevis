<?php

namespace Database\Seeders;

use App\Enums\Unit;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\CustomerUser;
use App\Models\DeliveryException;
use App\Models\DeliverySchedule;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local demo data: `php artisan db:seed --class=DemoSeeder`. Never run in production.
 *
 * Logins (password "demo-password" for all):
 *   admin@martinsversevis.test   — super admin, /admin
 *   verkoop@martinsversevis.test — "Verkoop" role, only sees Horeca customers
 *   chef@restaurant.test         — portal user for a Horeca customer, /portal
 *   jan@particulier.test         — portal user for a private customer
 *   chef@lamaree.test            — portal user for a French-speaking Horeca customer
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-password';

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        DeliverySchedule::firstOrCreate(['name' => 'Standaard'], [
            'description' => 'Dinsdag tot zaterdag, bestellen tot de dag ervoor om 16:00.',
            'is_default' => true,
            'days' => DeliverySchedule::blankDays([2, 3, 4, 5, 6]),
        ]);
        $route = DeliverySchedule::firstOrCreate(['name' => 'Horeca-ronde'], [
            'description' => 'Maandag tot zaterdag; late deadline, minimum € 50.',
            'days' => collect(DeliverySchedule::blankDays([1, 2, 3, 4, 5, 6]))
                ->map(fn (array $day) => ['cutoff_time' => '20:00'] + $day)
                ->all(),
            'minimum_order_amount' => 50,
        ]);

        $closureStart = today()->addDays(10);
        DeliveryException::firstOrCreate(['kind' => DeliveryException::CLOSED, 'reason' => 'Jaarlijks verlof'], [
            'starts_on' => $closureStart,
            'ends_on' => $closureStart->copy()->addDays(3),
            'translations' => ['fr' => ['reason' => 'Congé annuel']],
        ]);
        DeliveryException::firstOrCreate(['kind' => DeliveryException::EXTRA, 'reason' => 'Extra levering voor het feestweekend'], [
            'starts_on' => today()->next(Carbon::SUNDAY),
            'translations' => ['fr' => ['reason' => 'Livraison supplémentaire pour le week-end festif']],
        ]);

        $business = CustomerType::where('slug', CustomerType::BUSINESS)->first();
        $private = CustomerType::where('slug', CustomerType::PRIVATE)->first();
        $horeca = CustomerType::firstOrCreate(['slug' => 'horeca'], [
            'name' => 'Horeca',
            'description' => 'Restaurants en traiteurs met eigen tarieven.',
            'notification_emails' => ['keuken@martinsversevis.test'],
            'delivery_schedule_id' => $route->id,
            'sort_order' => 3,
        ]);

        User::firstOrCreate(['email' => 'admin@martinsversevis.test'], [
            'name' => 'Martin',
            'password' => self::PASSWORD,
        ])->syncRoles([Permissions::SUPER_ADMIN_ROLE]);

        $sales = User::firstOrCreate(['email' => 'verkoop@martinsversevis.test'], [
            'name' => 'Sofie Verkoop',
            'password' => self::PASSWORD,
        ]);
        $sales->syncRoles(['Verkoop']);
        $sales->customerTypes()->sync([$horeca->id]);

        $categoryNames = [
            'Vis' => 'Poissons',
            'Schaal- en schelpdieren' => 'Crustacés et coquillages',
            'Bereid' => 'Préparations',
        ];

        // [name, French name, unit, origin, business, horeca, private]
        $catalogue = [
            'Vis' => [
                ['Kabeljauwfilet', 'Filet de cabillaud', Unit::Kilogram, 'Noorwegen', 24.50, 21.90, 27.50],
                ['Zalmfilet', 'Filet de saumon', Unit::Kilogram, 'Schotland', 22.00, 19.80, 25.90],
                ['Tarbot, heel', 'Turbot entier', Unit::Kilogram, 'Noordzee', null, null, null],
                ['Zeetong', 'Sole', Unit::Kilogram, 'Noordzee', 39.00, 35.00, 44.00],
                ['Tongschar', 'Limande-sole', Unit::Piece, 'Noordzee', 6.50, 5.80, 7.20],
            ],
            'Schaal- en schelpdieren' => [
                ['Noordzeegarnalen, gepeld', 'Crevettes grises décortiquées', Unit::Kilogram, 'Zeebrugge', 48.00, 44.00, 54.00],
                ['Mosselen Zeeuwse', 'Moules de Zélande', Unit::Kilogram, 'Zeeland', 5.20, 4.60, 6.50],
                ['Oesters Fines de Claire n°3', 'Huîtres Fines de Claire n°3', Unit::Box, 'Frankrijk', 19.50, 17.00, 22.00],
            ],
            'Bereid' => [
                ['Vispannetje', 'Cassolette de poisson', Unit::Portion, null, 9.50, 8.50, 11.00],
                ['Gerookte zalm, gesneden', 'Saumon fumé tranché', Unit::Kilogram, 'Schotland', 42.00, 38.00, 47.00],
            ],
        ];

        $lists = [
            'business' => PriceList::firstOrCreate(['name' => 'Zakelijk standaard'], [
                'description' => 'Basistarief voor zakelijke klanten.',
                'translations' => ['fr' => ['name' => 'Professionnels standard', 'description' => 'Tarif de base pour les clients professionnels.']],
            ]),
            'horeca' => PriceList::firstOrCreate(['name' => 'Horeca'], [
                'description' => 'Scherpere prijzen voor vaste horecaklanten.',
                'translations' => ['fr' => ['name' => 'Horeca', 'description' => 'Prix avantageux pour nos clients horeca réguliers.']],
            ]),
            'private' => PriceList::firstOrCreate(['name' => 'Particulier'], [
                'description' => 'Prijzen incl. voorbereiding voor particulieren.',
                'translations' => ['fr' => ['name' => 'Particuliers', 'description' => 'Prix préparation comprise pour les particuliers.']],
            ]),
        ];
        $lists['business']->customerTypes()->syncWithoutDetaching([$business->id]);
        $lists['horeca']->customerTypes()->syncWithoutDetaching([$horeca->id]);
        $lists['private']->customerTypes()->syncWithoutDetaching([$private->id]);

        $sort = 0;
        foreach ($catalogue as $categoryName => $products) {
            $category = ProductCategory::firstOrCreate(['slug' => Str::slug($categoryName)], [
                'name' => $categoryName,
                'translations' => ['fr' => ['name' => $categoryNames[$categoryName]]],
                'sort_order' => $sort,
            ]);

            foreach ($products as [$name, $frenchName, $unit, $origin, $businessPrice, $horecaPrice, $privatePrice]) {
                $product = Product::firstOrCreate(['slug' => Str::slug($name)], [
                    'name' => $name,
                    'translations' => ['fr' => ['name' => $frenchName]],
                    'product_category_id' => $category->id,
                    'sku' => strtoupper(Str::substr(Str::slug($name, ''), 0, 6)).'-'.(++$sort),
                    'unit' => $unit,
                    'origin' => $origin,
                    'vat_rate' => 6,
                    'sort_order' => $sort,
                ]);

                foreach (['business' => $businessPrice, 'horeca' => $horecaPrice, 'private' => $privatePrice] as $key => $price) {
                    $lists[$key]->items()->firstOrCreate(['product_id' => $product->id], [
                        'price' => $price,
                        'min_quantity' => $key === 'horeca' && $unit === Unit::Kilogram ? 2 : null,
                        'sort_order' => $sort,
                    ]);
                }
            }
        }

        $restaurant = Customer::firstOrCreate(['name' => 'Restaurant De Haven'], [
            'customer_type_id' => $horeca->id,
            'contact_name' => 'Chef Pieter',
            'email' => 'info@restaurant.test',
            'phone' => '+32 50 12 34 56',
            'vat_number' => 'BE0123456789',
            'street' => 'Havenkaai 1',
            'postal_code' => '8380',
            'city' => 'Zeebrugge',
        ]);
        CustomerUser::firstOrCreate(['email' => 'chef@restaurant.test'], [
            'customer_id' => $restaurant->id,
            'name' => 'Pieter',
            'password' => self::PASSWORD,
        ]);

        $maree = Customer::firstOrCreate(['name' => 'Restaurant La Marée'], [
            'customer_type_id' => $horeca->id,
            'locale' => 'fr',
            'contact_name' => 'Chef Sophie',
            'email' => 'info@lamaree.test',
            'street' => 'Digue de Mer 5',
            'postal_code' => '8300',
            'city' => 'Knokke-Heist',
        ]);
        CustomerUser::firstOrCreate(['email' => 'chef@lamaree.test'], [
            'customer_id' => $maree->id,
            'name' => 'Sophie',
            'password' => self::PASSWORD,
        ]);

        $jan = Customer::firstOrCreate(['name' => 'Jan Peeters'], [
            'customer_type_id' => $private->id,
            'email' => 'jan@particulier.test',
            'street' => 'Kerkstraat 12',
            'postal_code' => '8000',
            'city' => 'Brugge',
        ]);
        CustomerUser::firstOrCreate(['email' => 'jan@particulier.test'], [
            'customer_id' => $jan->id,
            'name' => 'Jan',
            'password' => self::PASSWORD,
        ]);
    }
}
