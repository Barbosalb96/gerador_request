<?php

namespace barbosalb96;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

class GenerateRequestCommand extends Command
{
    protected $signature = 'make:request-from-model {model}';
    protected $description = 'Gera um Request com base nos campos e tipos de dados de um Model';

    public function handle()
    {
        $modelName = $this->argument('model');
        $modelClass = "App\\Models\\$modelName";

        if (!class_exists($modelClass)) {
            $this->error("O model {$modelName} não foi encontrado.");
            return;
        }

        $tableName = (new $modelClass())->getTable();
        $columns = Schema::getConnection()->getSchemaBuilder()->getColumns($tableName);
        $getForeignKeys = Schema::getConnection()->getSchemaBuilder()->getForeignKeys($tableName);

        $requestPath = app_path("Http/Requests/{$modelName}Request.php");

        $rules = [];
        $messages = [];
        $prepareStatements = [];

        foreach ($columns as $column) {
            $field = $column['name'];

            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $rule = [];
            $messagePrefix = ucfirst(str_replace('_', ' ', $field));

            if ($column['nullable']) {
                $rule[] = 'required';
                $messages["$field.required"] = "$messagePrefix é obrigatório.";
            } else {
                $rule[] = 'nullable';
            }

            switch ($column['type_name']) {
                case 'varchar':
                    $rule[] = 'string';
                    $messages["$field.string"] = "$messagePrefix deve ser uma string.";

                    preg_match('/\d+/', $column['type'], $matches);

                    if (isset($matches[0])) {
                        $rule[] = "max:{$matches[0]}";
                        $messages["$field.max"] = "$messagePrefix não pode ter mais que {$matches[0]} caracteres.";
                    }
                    $prepareStatements[] = "\$this->merge(['$field' => trim(\$this->input('$field'))]);";
                    break;

                case 'text':
                    $rule[] = 'string';
                    $messages["$field.string"] = "$messagePrefix deve ser um texto.";
                    break;
                case 'integer':
                case 'smallint':
                case 'bigint':
                    $rule[] = 'integer';

                    $relashion = collect($getForeignKeys)->flatMap(function ($item) use ($column) {
                        if ($item['columns'][0] === $column['name']) {
                            return $item;
                        };
                    })->toArray();
                    if (!empty($relashion)) {
                        $rule[] = 'exists:' . $relashion['foreign_table'] . "," . $relashion['foreign_columns'][0];
                        $messages["$field.integer"] = "$messagePrefix deve existir na tabela " . $relashion['foreign_table'];
                    }

                    $messages["$field.integer"] = "$messagePrefix deve ser um número inteiro.";
                    $prepareStatements[] = "\$this->merge(['$field' => is_null(\$this->input('$field')) ? null : (int) \$this->input('$field')]);";
                    break;

                case 'float':
                case 'double':
                case 'decimal':
                    $rule[] = 'numeric';
                    $messages["$field.numeric"] = "$messagePrefix deve ser um número.";
                    $prepareStatements[] = "\$this->merge(['$field' => is_null(\$this->input('$field')) ? null : (float) \$this->input('$field')]);";
                    break;

                case 'boolean':
                    $rule[] = 'boolean';
                    $messages["$field.boolean"] = "$messagePrefix deve ser verdadeiro ou falso.";
                    $prepareStatements[] = "\$this->merge(['$field' => filter_var(\$this->input('$field'), FILTER_VALIDATE_BOOLEAN)]);";
                    break;

                case 'date':
                    $rule[] = 'date';
                    $messages["$field.date"] = "$messagePrefix deve ser uma data válida.";
                    $prepareStatements[] = "\$this->merge(['$field' => is_null(\$this->input('$field')) ? null : date('Y-m-d', strtotime(\$this->input('$field')))]);";
                    break;

                case 'datetime':
                    $rule[] = 'date_format:Y-m-d H:i:s';
                    $messages["$field.date_format"] = "$messagePrefix deve estar no formato AAAA-MM-DD HH:MM:SS.";
                    $prepareStatements[] = "\$this->merge(['$field' => is_null(\$this->input('$field')) ? null : date('Y-m-d H:i:s', strtotime(\$this->input('$field')))]);";
                    break;

                case 'time':
                    $rule[] = 'date_format:H:i:s';
                    $messages["$field.date_format"] = "$messagePrefix deve estar no formato HH:MM:SS.";
                    break;

                case 'json':
                    $rule[] = 'json';
                    $messages["$field.json"] = "$messagePrefix deve ser um JSON válido.";
                    break;

                case 'enum':
                    preg_match("/enum\((.*?)\)/", $column['type'], $matches);

                    $enumValues = explode("','", trim($matches[1], "'")) ?? [];
                    if (!empty($enumValues)) {
                        $rule[] = 'in:' . implode(',', $enumValues);
                        $messages["$field.in"] = "$messagePrefix deve ser um dos seguintes valores: " . implode(', ', $enumValues) . ".";
                    }
                    break;
            }

            $rules[] = "'$field' => ['" . implode("','", $rule) . "']";
        }

        $content = "<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class {$modelName}Request extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            " . implode(",\n            ", $rules) . "
        ];
    }

    public function messages()
    {
        return [
            " . implode(",\n            ", array_map(fn($k, $v) => "'$k' => '$v'", array_keys($messages), $messages)) . "
        ];
    }

    protected function prepareForValidation()
    {
        " . implode("\n        ", $prepareStatements) . "
    }
}";

        // Salvar o arquivo
        File::put($requestPath, $content);
        $this->info("Request criado: $requestPath");
    }
}