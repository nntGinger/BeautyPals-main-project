<?php

namespace App\Http\Requests\Admin;

use App\Models\Ingredient;
use App\Models\IngredientAlias;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class IngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ingredient = $this->route('ingredient');

        return Gate::allows($ingredient instanceof Ingredient ? 'update' : 'create', $ingredient ?? Ingredient::class);
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['inci_name', 'display_name', 'function'] as $field) {
            if (is_string($this->input($field))) {
                $values[$field] = trim($this->input($field));
            }
        }
        if (is_array($this->input('aliases'))) {
            $values['aliases'] = array_map(fn ($alias) => is_string($alias) ? trim($alias) : $alias, $this->input('aliases'));
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        $id = $this->route('ingredient')?->id;

        return [
            'inci_name' => ['required', 'string', 'max:150', Rule::unique('ingredients', 'inci_name')->ignore($id)],
            'display_name' => ['required', 'string', 'max:150'],
            'function' => ['nullable', 'string', 'max:255'],
            'aliases' => ['sometimes', 'array', 'list', 'max:20'],
            'aliases.*' => ['required', 'string', 'max:150', Rule::unique('ingredient_aliases', 'alias')->where(fn ($query) => $query->where('ingredient_id', '!=', $id ?? 0))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $id = $this->route('ingredient')?->id;
            $names = [$this->input('inci_name'), $this->input('display_name')];
            foreach ($names as $field => $name) {
                if (IngredientAlias::where('alias', $name)->where('ingredient_id', '!=', $id ?? 0)->exists()) {
                    $validator->errors()->add($field === 0 ? 'inci_name' : 'display_name', 'Tên này đã được dùng làm alias của thành phần khác.');
                }
            }
            $aliases = $this->input('aliases', []);
            foreach ($aliases as $index => $alias) {
                if (Ingredient::where('id', '!=', $id ?? 0)->where(fn ($query) => $query->where('inci_name', $alias)->orWhere('display_name', $alias))->exists()) {
                    $validator->errors()->add("aliases.$index", 'Alias trùng tên của thành phần khác.');
                }
                foreach (array_slice($aliases, 0, $index) as $previous) {
                    if (mb_strtolower($previous) === mb_strtolower($alias)) {
                        $validator->errors()->add("aliases.$index", 'Alias bị lặp trong danh sách.');
                        break;
                    }
                }
            }
        }];
    }
}
