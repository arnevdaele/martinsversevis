<?php

namespace Database\Seeders;

use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalogue: categories and products, without prices (those are per price
 * list, see {@see DemoSeeder}). Safe to run again: existing slugs are left alone.
 * On its own: `php artisan db:seed --class=ProductSeeder`.
 */
class ProductSeeder extends Seeder
{
    /** Dutch category name => French name. */
    public const CATEGORIES = [
        'Vis' => 'Poissons',
        'Schaal- en schelpdieren' => 'Crustacés et coquillages',
        'Bereid' => 'Préparations',
    ];

    /**
     * Per category: [name, description, unit, origin, French name, French description].
     */
    public const PRODUCTS = [
        'Vis' => [
            ['Kabeljauwfilet', 'Dikke, witte filet zonder graat. Stevig en mild van smaak, ideaal om te bakken of te pocheren.', Unit::Kilogram, 'Noorwegen',
                'Filet de cabillaud', 'Filet blanc et épais sans arêtes. Chair ferme au goût doux, idéal à la poêle ou poché.'],
            ['Zalmfilet', 'Verse zalmfilet met vel, graatloos gesneden. Mooi vetgehalte, ook geschikt voor tartaar.', Unit::Kilogram, 'Schotland',
                'Filet de saumon', 'Filet de saumon frais avec peau, sans arêtes. Belle teneur en gras, convient aussi pour un tartare.'],
            ['Tarbot, heel', 'Hele wilde tarbot, op vraag schoongemaakt. Prijs volgens de dagmarkt.', Unit::Kilogram, 'Noordzee',
                'Turbot entier', 'Turbot sauvage entier, vidé sur demande. Prix selon le marché du jour.'],
            ['Zeetong', 'Wilde zeetong, gestroopt en klaar om te bakken. Per kilo, stuks van 300 à 400 g.', Unit::Kilogram, 'Noordzee',
                'Sole', 'Sole sauvage, pelée et prête à cuire. Au kilo, pièces de 300 à 400 g.'],
            ['Tongschar', 'Hele tongschar, schoongemaakt. Fijn en zacht vlees, per stuk.', Unit::Piece, 'Noordzee',
                'Limande-sole', 'Limande-sole entière, vidée. Chair fine et tendre, à la pièce.'],
            ['Zeebaarsfilet', 'Filet van zeebaars met vel, geschubd. Knapperig gebakken op het vel.', Unit::Kilogram, 'Griekenland',
                'Filet de bar', 'Filet de bar avec peau, écaillé. Délicieux croustillant côté peau.'],
            ['Heekfilet', 'Zachte, witte heekfilet zonder vel. Betaalbaar alternatief voor kabeljauw.', Unit::Kilogram, 'Spanje',
                'Filet de merlu', 'Filet de merlu blanc et tendre, sans peau. Alternative abordable au cabillaud.'],
            ['Roggevleugel', 'Gestroopte roggevleugel, klaar om te pocheren. Klassiek met kappertjes en bruine boter.', Unit::Kilogram, 'Noordzee',
                'Aile de raie', 'Aile de raie pelée, prête à pocher. Classique au beurre noisette et aux câpres.'],
            ['Tonijnsteak', 'Geelvintonijn in steaks van ongeveer 200 g. Kort bakken, vanbinnen rosé.', Unit::Kilogram, 'Sri Lanka',
                'Steak de thon', 'Thon albacore en steaks d\'environ 200 g. À saisir rapidement, rosé à cœur.'],
            ['Makreel, heel', 'Verse makreel, schoongemaakt. Lekker op de grill of in de oven.', Unit::Piece, 'Noordzee',
                'Maquereau entier', 'Maquereau frais, vidé. Savoureux au grill ou au four.'],
        ],
        'Schaal- en schelpdieren' => [
            ['Noordzeegarnalen, gepeld', 'Grijze garnalen, dagvers gepeld. Voor tomaat-garnaal of garnaalkroketten.', Unit::Kilogram, 'Zeebrugge',
                'Crevettes grises décortiquées', 'Crevettes grises décortiquées chaque jour. Pour tomates-crevettes ou croquettes.'],
            ['Mosselen Zeeuwse', 'Zeeuwse mosselen, gewassen en klaar om te koken. Reken op 1 kg per persoon.', Unit::Kilogram, 'Zeeland',
                'Moules de Zélande', 'Moules de Zélande, lavées et prêtes à cuire. Comptez 1 kg par personne.'],
            ['Oesters Fines de Claire n°3', 'Doos van 12 oesters, zilt en vlezig.', Unit::Box, 'Frankrijk',
                'Huîtres Fines de Claire n°3', 'Bourriche de 12 huîtres, iodées et charnues.'],
            ['Sint-Jakobsvruchten', 'Noten van Sint-Jakobsschelpen, zonder koraal. Kort aanbakken.', Unit::Kilogram, 'Normandië',
                'Noix de Saint-Jacques', 'Noix de Saint-Jacques sans corail. À saisir brièvement.'],
            ['Kreeft, levend', 'Levende Oosterscheldekreeft of Bretoense kreeft, volgens aanvoer. Prijs volgens de dagmarkt.', Unit::Kilogram, 'Oosterschelde',
                'Homard vivant', 'Homard vivant de l\'Escaut oriental ou de Bretagne, selon arrivage. Prix selon le marché du jour.'],
            ['Langoustines', 'Verse langoustines, maat 10/15 per kilo.', Unit::Kilogram, 'Schotland',
                'Langoustines', 'Langoustines fraîches, calibre 10/15 au kilo.'],
        ],
        'Bereid' => [
            ['Vispannetje', 'Stukken vis en garnalen in een romige saus, met puree. Opwarmen in de oven.', Unit::Portion, null,
                'Cassolette de poisson', 'Morceaux de poisson et crevettes dans une sauce crémeuse, avec purée. À réchauffer au four.'],
            ['Gerookte zalm, gesneden', 'Huisgerookte zalm, dun gesneden en vacuüm verpakt.', Unit::Kilogram, 'Schotland',
                'Saumon fumé tranché', 'Saumon fumé maison, finement tranché et emballé sous vide.'],
            ['Vissoep', 'Huisgemaakte Noordzeevissoep met groenten, zonder stukken vis.', Unit::Litre, null,
                'Soupe de poisson', 'Soupe de poisson de la mer du Nord maison, aux légumes, sans morceaux de poisson.'],
            ['Garnaalkroketten', 'Ambachtelijke kroketten met veel Noordzeegarnalen. Per stuk, diepvries.', Unit::Piece, 'Zeebrugge',
                'Croquettes de crevettes', 'Croquettes artisanales généreuses en crevettes grises. À la pièce, surgelées.'],
        ],
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::PRODUCTS as $categoryName => $products) {
            $category = ProductCategory::firstOrCreate(['slug' => Str::slug($categoryName)], [
                'name' => $categoryName,
                'translations' => ['fr' => ['name' => self::CATEGORIES[$categoryName]]],
                'sort_order' => $sort,
            ]);

            foreach ($products as [$name, $description, $unit, $origin, $frenchName, $frenchDescription]) {
                Product::firstOrCreate(['slug' => Str::slug($name)], [
                    'name' => $name,
                    'description' => $description,
                    'translations' => ['fr' => ['name' => $frenchName, 'description' => $frenchDescription]],
                    'product_category_id' => $category->id,
                    'sku' => strtoupper(Str::substr(Str::slug($name, ''), 0, 6)).'-'.(++$sort),
                    'unit' => $unit,
                    'origin' => $origin,
                    'vat_rate' => 6,
                    'sort_order' => $sort,
                ]);
            }
        }
    }
}
