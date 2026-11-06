<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\IngredientAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/ingredients.json');

        $ingredients = json_decode(
            File::get($path),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        if (! is_array($ingredients) || ! array_is_list($ingredients)) {
            throw new RuntimeException(
                'ingredients.json phải chứa một danh sách ingredient.',
            );
        }

        Validator::make(
            ['ingredients' => $ingredients],
            [
                'ingredients' => ['required', 'array', 'list', 'size:22'],
                'ingredients.*' => [
                    'required',
                    'array:inci_name,display_name,function,aliases',
                ],
                'ingredients.*.inci_name' => [
                    'required', 'string', 'max:150',
                    'distinct:ignore_case',
                ],
                'ingredients.*.display_name' => [
                    'required', 'string', 'max:150',
                ],
                'ingredients.*.function' => [
                    'present', 'nullable', 'string', 'max:255',
                ],
                'ingredients.*.aliases' => [
                    'present', 'array', 'list', 'max:20',
                ],
                'ingredients.*.aliases.*' => [
                    'required', 'string', 'max:150',
                ],
            ],
        )->validate();

        // Chuẩn hóa trước khi kiểm tra trùng và ghi dữ liệu.
        foreach ($ingredients as &$item) {
            $item['inci_name'] = trim($item['inci_name']);
            $item['display_name'] = trim($item['display_name']);
            $item['function'] = isset($item['function'])
                ? trim($item['function'])
                : null;

            $item['function'] = $item['function'] === ''
                ? null
                : $item['function'];

            $item['aliases'] = array_map(
                fn (string $alias): string => trim($alias),
                $item['aliases'],
            );
        }

        unset($item);

        // Kiểm tra lại tên sau trim và alias trên toàn bộ bộ seed.
        Validator::make(
            [
                'names' => array_column($ingredients, 'inci_name'),
                'display_names' => array_column($ingredients, 'display_name'),
                'aliases' => array_merge(
                    [],
                    ...array_column($ingredients, 'aliases'),
                ),
            ],
            [
                'names.*' => ['required', 'distinct:ignore_case'],
                'display_names.*' => ['required'],
                'aliases.*' => ['required', 'distinct:ignore_case'],
            ],
        )->validate();

        DB::transaction(function () use ($ingredients): void {
            foreach ($ingredients as $item) {
                $ingredient = Ingredient::query()->firstOrCreate(
                    ['inci_name' => $item['inci_name']],
                    [
                        'display_name' => $item['display_name'],
                        'function' => $item['function'],
                    ],
                );

                foreach ($item['aliases'] as $aliasName) {
                    $alias = IngredientAlias::query()->firstOrCreate(
                        ['alias' => $aliasName],
                        ['ingredient_id' => $ingredient->id],
                    );

                    if ((int) $alias->ingredient_id !== (int) $ingredient->id) {
                        throw new RuntimeException(
                            "Alias '{$aliasName}' đã thuộc ingredient khác.",
                        );
                    }
                }
            }

            // Kiểm tra chéo sau khi đã tạo đủ ingredient/alias.
            // Khi phát hiện lỗi, toàn bộ lần seed được rollback.
            foreach ($ingredients as $item) {
                $ingredient = Ingredient::query()
                    ->where('inci_name', $item['inci_name'])
                    ->firstOrFail();

                $nameConflict = IngredientAlias::query()
                    ->where('ingredient_id', '<>', $ingredient->id)
                    ->whereIn('alias', [
                        $ingredient->inci_name,
                        $ingredient->display_name,
                    ])
                    ->exists();

                $aliasNames = $ingredient->aliases()->pluck('alias');

                $aliasConflict = Ingredient::query()
                    ->where('id', '<>', $ingredient->id)
                    ->where(function ($query) use ($aliasNames): void {
                        $query->whereIn('inci_name', $aliasNames)
                            ->orWhereIn('display_name', $aliasNames);
                    })
                    ->exists();

                if ($nameConflict || $aliasConflict) {
                    throw new RuntimeException(
                        "Tên/alias của '{$ingredient->inci_name}' "
                        .'xung đột với ingredient khác.',
                    );
                }
            }
        });

        $this->command?->info('Đã nạp bộ 22 ingredient và alias.');
    }
}
