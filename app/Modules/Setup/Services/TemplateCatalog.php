<?php

namespace App\Modules\Setup\Services;

use App\Models\CatalogCategory;
use App\Models\CatalogGroup;
use App\Models\ProductFieldDefinition;
use Illuminate\Support\Str;

final class TemplateCatalog
{
    public function all(): array
    {
        return [
            'perfumery' => 'Perfumería',
            'fashion' => 'Ropa y calzado',
            'technology' => 'Tecnología',
            'gym' => 'Gimnasio y fitness',
            'automotive' => 'Repuestos y vehículos',
            'grocery' => 'Alimentos y abarrotes',
            'other' => 'Catálogo personalizado',
        ];
    }

    public function previews(): array
    {
        $previews = [];

        foreach ($this->all() as $type => $label) {
            $definition = $this->definition($type);

            $previews[$type] = [
                'label' => $label,
                'categories' => $definition['categories'],
                'groups' => array_map(
                    static fn (array $group): array => [
                        'name' => $group['name'],
                        'fields' => array_values(array_map(
                            static fn (array $field): string => $field['label'],
                            $group['fields'],
                        )),
                    ],
                    $definition['groups'],
                ),
            ];
        }

        return $previews;
    }

    public function seed(string $type): void
    {
        $template = $this->definition($type);

        foreach ($template['categories'] as $index => $category) {
            CatalogCategory::query()->create([
                'name' => $category,
                'slug' => Str::slug($category),
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        foreach ($template['groups'] as $groupIndex => $groupDefinition) {
            $group = CatalogGroup::query()->create([
                'name' => $groupDefinition['name'],
                'sort_order' => $groupIndex,
            ]);

            foreach ($groupDefinition['fields'] as $fieldIndex => $field) {
                ProductFieldDefinition::query()->create([
                    'group_id' => $group->id,
                    'field_key' => $field['key'],
                    'label' => $field['label'],
                    'field_type' => $field['type'],
                    'options' => $field['options'] ?? null,
                    'unit_dimension' => $field['dimension'] ?? null,
                    'unit_code' => $field['unit'] ?? null,
                    'is_required' => false,
                    'is_public' => true,
                    'is_searchable' => $field['searchable'] ?? false,
                    'width' => 'half',
                    'sort_order' => $fieldIndex,
                ]);
            }
        }
    }

    private function definition(string $type): array
    {
        $text = static fn (string $key, string $label, bool $searchable = false): array => [
            'key' => $key, 'label' => $label, 'type' => 'text', 'searchable' => $searchable,
        ];
        $number = static fn (string $key, string $label, string $dimension, string $unit): array => [
            'key' => $key, 'label' => $label, 'type' => 'number',
            'dimension' => $dimension, 'unit' => $unit,
        ];
        $select = static fn (string $key, string $label, array $options): array => [
            'key' => $key, 'label' => $label, 'type' => 'select', 'options' => $options,
        ];

        return match ($type) {
            'perfumery' => [
                'categories' => ['Perfumes', 'Body mist', 'Sets'],
                'groups' => [['name' => 'Detalles', 'fields' => [
                    $text('brand', 'Marca', true),
                    $number('content', 'Contenido', 'volume', 'ml'),
                    $select('concentration', 'Concentración', ['EDT', 'EDP', 'Parfum']),
                    $select('audience', 'Público', ['Mujer', 'Hombre', 'Unisex']),
                ]]],
            ],
            'fashion' => [
                'categories' => ['Ropa', 'Calzado', 'Accesorios'],
                'groups' => [['name' => 'Detalles', 'fields' => [
                    $text('brand', 'Marca', true),
                    $text('size', 'Talla', true),
                    $text('color', 'Color', true),
                    $text('material', 'Material'),
                ]]],
            ],
            'technology' => [
                'categories' => ['Teléfonos', 'Computadoras', 'Accesorios'],
                'groups' => [['name' => 'Especificaciones', 'fields' => [
                    $text('brand', 'Marca', true),
                    $text('model', 'Modelo', true),
                    $number('ram', 'Memoria RAM', 'digital', 'GB'),
                    $number('storage', 'Almacenamiento', 'digital', 'GB'),
                    $text('warranty', 'Garantía'),
                ]]],
            ],
            'gym' => [
                'categories' => ['Equipos', 'Accesorios', 'Ropa deportiva'],
                'groups' => [['name' => 'Detalles', 'fields' => [
                    $text('brand', 'Marca', true),
                    $text('material', 'Material'),
                    $text('size', 'Tamaño'),
                ]]],
            ],
            'automotive' => [
                'categories' => ['Repuestos', 'Vehículos', 'Accesorios'],
                'groups' => [['name' => 'Ficha técnica', 'fields' => [
                    $text('brand', 'Marca', true),
                    $text('model', 'Modelo', true),
                    ['key' => 'year', 'label' => 'Año', 'type' => 'number'],
                    $number('engine_displacement', 'Cilindraje', 'displacement', 'cc'),
                    $text('compatibility', 'Compatibilidad'),
                ]]],
            ],
            'grocery' => [
                'categories' => ['Alimentos', 'Bebidas', 'Abarrotes'],
                'groups' => [['name' => 'Detalles', 'fields' => [
                    $text('brand', 'Marca', true),
                    $number('net_content', 'Contenido neto', 'volume', 'ml'),
                    $text('presentation', 'Presentación'),
                ]]],
            ],
            default => [
                'categories' => ['General'],
                'groups' => [['name' => 'Detalles', 'fields' => [
                    $text('brand', 'Marca', true),
                    $text('reference', 'Referencia', true),
                ]]],
            ],
        };
    }
}
