<?php

namespace Database\Seeders;

use App\Models\BudgetAllocation;
use App\Models\BudgetMonth;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Renombrar slugs legacy para no duplicar categorías existentes
        Category::query()->where('slug', 'dog')->update([
            'slug' => 'pets',
            'name' => 'Cuidado y artículos para mascotas',
        ]);
        Subcategory::query()->where('slug', 'dogfood')->update([
            'slug' => 'pet-food',
            'name' => 'Comida/Artículos',
        ]);
        Subcategory::query()->where('slug', 'transport')->update([
            'slug' => 'public-transport',
            'name' => 'Transporte público',
        ]);

        $catalog = [
            [
                'slug' => 'util',
                'name' => 'Servicios generales',
                'color' => '#81c784',
                'subs' => [
                    ['slug' => 'internet', 'name' => 'Internet', 'amount' => 80, 'max' => 500],
                    ['slug' => 'water', 'name' => 'Agua', 'amount' => 45, 'max' => 300],
                    ['slug' => 'electricity', 'name' => 'Electricidad', 'amount' => 45, 'max' => 500],
                    ['slug' => 'gas', 'name' => 'Gas', 'amount' => 0, 'max' => 400],
                    ['slug' => 'trash', 'name' => 'Aseo/Basuras', 'amount' => 0, 'max' => 200],
                    ['slug' => 'condo-admin', 'name' => 'Administración/Condominio', 'amount' => 0, 'max' => 1500],
                    ['slug' => 'landline', 'name' => 'Teléfono fijo', 'amount' => 0, 'max' => 200],
                ],
            ],
            [
                'slug' => 'housing',
                'name' => 'Vivienda',
                'color' => '#64b5f6',
                'subs' => [
                    ['slug' => 'rent-mortgage', 'name' => 'Arriendo/Hipoteca', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'maintenance', 'name' => 'Mantenimiento', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'repairs', 'name' => 'Reparaciones', 'amount' => 0, 'max' => 2000],
                    ['slug' => 'furniture-appliances', 'name' => 'Muebles/Electrodomésticos', 'amount' => 0, 'max' => 3000],
                    ['slug' => 'decoration', 'name' => 'Decoración', 'amount' => 0, 'max' => 1000],
                ],
            ],
            [
                'slug' => 'pets',
                'name' => 'Cuidado y artículos para mascotas',
                'color' => '#ffd54f',
                'subs' => [
                    ['slug' => 'walker', 'name' => 'Paseador', 'amount' => 400, 'max' => 1000],
                    ['slug' => 'pet-food', 'name' => 'Comida/Artículos', 'amount' => 250, 'max' => 800],
                    ['slug' => 'vet', 'name' => 'Veterinario', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'boarding', 'name' => 'Guardería/Pensión', 'amount' => 0, 'max' => 800],
                ],
            ],
            [
                'slug' => 'trans',
                'name' => 'Gastos de transporte',
                'color' => '#e57373',
                'subs' => [
                    ['slug' => 'public-transport', 'name' => 'Transporte público', 'amount' => 180, 'max' => 1000],
                    ['slug' => 'gas-fuel', 'name' => 'Gasolina', 'amount' => 0, 'max' => 1500],
                    ['slug' => 'parking', 'name' => 'Parqueadero', 'amount' => 0, 'max' => 500],
                    ['slug' => 'vehicle-maintenance', 'name' => 'Mantenimiento vehículo', 'amount' => 0, 'max' => 2000],
                    ['slug' => 'taxi-rideshare', 'name' => 'Taxi/App transporte', 'amount' => 0, 'max' => 800],
                    ['slug' => 'tolls', 'name' => 'Peajes', 'amount' => 0, 'max' => 300],
                ],
            ],
            [
                'slug' => 'food',
                'name' => 'Comida',
                'color' => '#9575cd',
                'subs' => [
                    ['slug' => 'groceries', 'name' => 'Mercado/Comida', 'amount' => 300, 'max' => 1500],
                    ['slug' => 'restaurants', 'name' => 'Restaurantes', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'delivery', 'name' => 'Domicilios', 'amount' => 0, 'max' => 800],
                    ['slug' => 'coffee-snacks', 'name' => 'Café/Snacks', 'amount' => 0, 'max' => 400],
                ],
            ],
            [
                'slug' => 'health',
                'name' => 'Salud y gimnasio',
                'color' => '#f06292',
                'subs' => [
                    ['slug' => 'gym', 'name' => 'Salud/Gimnasio', 'amount' => 50, 'max' => 500],
                    ['slug' => 'medications', 'name' => 'Medicamentos', 'amount' => 0, 'max' => 500],
                    ['slug' => 'medical-consults', 'name' => 'Consultas médicas', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'health-insurance', 'name' => 'Seguro médico', 'amount' => 0, 'max' => 1500],
                    ['slug' => 'dental', 'name' => 'Odontología', 'amount' => 0, 'max' => 800],
                ],
            ],
            [
                'slug' => 'pers',
                'name' => 'Cuidado personal y suscripciones',
                'color' => '#4db6ac',
                'subs' => [
                    ['slug' => 'personal', 'name' => 'Cuidado personal', 'amount' => 80, 'max' => 500],
                    ['slug' => 'streaming', 'name' => 'Streaming (Netflix, Spotify, etc.)', 'amount' => 0, 'max' => 200],
                    ['slug' => 'apps-software', 'name' => 'Apps/Software', 'amount' => 0, 'max' => 300],
                    ['slug' => 'hair-beauty', 'name' => 'Peluquería/Belleza', 'amount' => 0, 'max' => 400],
                ],
            ],
            [
                'slug' => 'education',
                'name' => 'Educación',
                'color' => '#7986cb',
                'subs' => [
                    ['slug' => 'tuition', 'name' => 'Matrícula/Pensión', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'materials-books', 'name' => 'Materiales/Libros', 'amount' => 0, 'max' => 500],
                    ['slug' => 'online-courses', 'name' => 'Cursos online', 'amount' => 0, 'max' => 500],
                    ['slug' => 'training', 'name' => 'Capacitaciones', 'amount' => 0, 'max' => 1000],
                ],
            ],
            [
                'slug' => 'entertainment',
                'name' => 'Entretenimiento',
                'color' => '#ff8a65',
                'subs' => [
                    ['slug' => 'cinema-events', 'name' => 'Cine/Eventos', 'amount' => 0, 'max' => 500],
                    ['slug' => 'outings-bars', 'name' => 'Salidas/Bares', 'amount' => 0, 'max' => 800],
                    ['slug' => 'videogames', 'name' => 'Videojuegos', 'amount' => 0, 'max' => 300],
                    ['slug' => 'hobbies', 'name' => 'Hobbies', 'amount' => 0, 'max' => 500],
                ],
            ],
            [
                'slug' => 'clothing',
                'name' => 'Ropa y accesorios',
                'color' => '#ba68c8',
                'subs' => [
                    ['slug' => 'clothing', 'name' => 'Ropa', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'footwear', 'name' => 'Calzado', 'amount' => 0, 'max' => 500],
                    ['slug' => 'accessories', 'name' => 'Accesorios', 'amount' => 0, 'max' => 500],
                ],
            ],
            [
                'slug' => 'finance',
                'name' => 'Finanzas',
                'color' => '#4dd0e1',
                'subs' => [
                    ['slug' => 'savings', 'name' => 'Ahorro', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'investment', 'name' => 'Inversión', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'debt-payment', 'name' => 'Pago de deudas', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'credit-card', 'name' => 'Tarjeta de crédito', 'amount' => 0, 'max' => 3000],
                    ['slug' => 'insurance', 'name' => 'Seguros (vida, hogar)', 'amount' => 0, 'max' => 2000],
                ],
            ],
            [
                'slug' => 'family',
                'name' => 'Familia y niños',
                'color' => '#aed581',
                'subs' => [
                    ['slug' => 'school', 'name' => 'Colegio', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'nanny', 'name' => 'Niñera', 'amount' => 0, 'max' => 2000],
                    ['slug' => 'kids-toys', 'name' => 'Juguetes/Artículos infantiles', 'amount' => 0, 'max' => 500],
                ],
            ],
            [
                'slug' => 'work',
                'name' => 'Trabajo/Negocio',
                'color' => '#90a4ae',
                'subs' => [
                    ['slug' => 'work-tools', 'name' => 'Herramientas/Equipos', 'amount' => 0, 'max' => 3000],
                    ['slug' => 'office-supplies', 'name' => 'Suministros de oficina', 'amount' => 0, 'max' => 500],
                    ['slug' => 'taxes-accounting', 'name' => 'Impuestos/Contabilidad', 'amount' => 0, 'max' => 5000],
                ],
            ],
            [
                'slug' => 'travel',
                'name' => 'Viajes',
                'color' => '#ffb74d',
                'subs' => [
                    ['slug' => 'lodging', 'name' => 'Alojamiento', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'tickets', 'name' => 'Tiquetes', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'activities-tours', 'name' => 'Actividades/Tours', 'amount' => 0, 'max' => 2000],
                ],
            ],
            [
                'slug' => 'gifts',
                'name' => 'Regalos y donaciones',
                'color' => '#f48fb1',
                'subs' => [
                    ['slug' => 'gifts', 'name' => 'Regalos', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'donations', 'name' => 'Donaciones', 'amount' => 0, 'max' => 1000],
                    ['slug' => 'charity', 'name' => 'Caridad', 'amount' => 0, 'max' => 1000],
                ],
            ],
            [
                'slug' => 'unexpected',
                'name' => 'Imprevistos',
                'color' => '#ef5350',
                'subs' => [
                    ['slug' => 'emergencies', 'name' => 'Emergencias', 'amount' => 0, 'max' => 5000],
                    ['slug' => 'unexpected-repairs', 'name' => 'Reparaciones inesperadas', 'amount' => 0, 'max' => 3000],
                    ['slug' => 'fines', 'name' => 'Multas', 'amount' => 0, 'max' => 2000],
                ],
            ],
        ];

        foreach ($catalog as $catIndex => $catData) {
            $category = Category::query()->updateOrCreate(
                ['slug' => $catData['slug']],
                [
                    'name' => $catData['name'],
                    'color' => $catData['color'],
                    'sort_order' => $catIndex,
                ]
            );

            foreach ($catData['subs'] as $subIndex => $subData) {
                Subcategory::query()->updateOrCreate(
                    ['slug' => $subData['slug']],
                    [
                        'category_id' => $category->id,
                        'name' => $subData['name'],
                        'default_amount' => $this->cop($subData['amount']),
                        'default_max' => $this->cop($subData['max']),
                        'sort_order' => $subIndex,
                    ]
                );
            }
        }

        $year = (int) now()->year;
        $month = (int) now()->month;

        BudgetMonth::query()->firstOrCreate(
            ['year' => $year, 'month' => $month],
            ['income' => 3_800_000]
        );

        $this->syncAllocationsForAllMonths();
    }

    /** Convierte valores base del catálogo a pesos colombianos (COP). */
    private function cop(int $value): int
    {
        return $value * 1000;
    }

    private function syncAllocationsForAllMonths(): void
    {
        $subcategories = Subcategory::query()->orderBy('sort_order')->get();

        foreach (BudgetMonth::query()->get() as $budgetMonth) {
            foreach ($subcategories as $sub) {
                BudgetAllocation::query()->firstOrCreate(
                    [
                        'budget_month_id' => $budgetMonth->id,
                        'subcategory_id' => $sub->id,
                    ],
                    ['amount' => $sub->default_amount]
                );
            }
        }
    }
}
