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

        if (!$this->isModelValid($modelClass, $modelName)) {
            return;
        }

        $tableName = (new $modelClass())->getTable();
        $columns = Schema::getConnection()->getSchemaBuilder()->getColumns($tableName);
        $foreignKeys = Schema::getConnection()->getSchemaBuilder()->getForeignKeys($tableName);

        $requestPath = app_path("Http/Requests/{$modelName}Request.php");

        [$rules, $messages, $prepareStatements] = $this->generateValidationData($columns, $foreignKeys);

        $this->createRequestFile($modelName, $rules, $messages, $prepareStatements, $requestPath);
    }

    private function isModelValid(string $modelClass, string $modelName): bool
    {
        if (!class_exists($modelClass)) {
            $this->error("O model {$modelName} não foi encontrado.");
            return false;
        }
        return true;
    }

    private function generateValidationData(array $columns, array $foreignKeys): array
    {
        $rules = [];
        $messages = [];
        $prepareStatements = [];

        foreach ($columns as $column) {
            $field = $column['name'];

            if ($this->isIgnoredField($field)) {
                continue;
            }

            [$fieldRules, $fieldMessages, $fieldPrepare] = $this->processField($column, $field, $foreignKeys);
            $rules[] = "'$field' => [" . implode(", ", $fieldRules) . "]";
            $messages = array_merge($messages, $fieldMessages);
            $prepareStatements = array_merge($prepareStatements, $fieldPrepare);
        }

        return [$rules, $messages, $prepareStatements];
    }

    private function isIgnoredField(string $field): bool
    {
        return in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at']);
    }

    private function processField(array $column, string $field, array $foreignKeys): array
    {
        $rules = [];
        $messages = [];
        $prepareStatements = [];
        $messagePrefix = ucfirst(str_replace('_', ' ', $field));

        $rules[] = $column['nullable'] ? 'nullable' : 'required';
        $messages["$field.required"] = "$messagePrefix é obrigatório.";

        $this->addTypeSpecificRules($column, $field, $rules, $messages, $prepareStatements, $messagePrefix, $foreignKeys);

        return [$rules, $messages, $prepareStatements];
    }

    private function addTypeSpecificRules(
        array  $column,
        string $field,
        array  &$rules,
        array  &$messages,
        array  &$prepareStatements,
        string $messagePrefix,
        array  $foreignKeys
    ): void
    {
        switch ($column['type_name']) {
            case 'varchar':
                $this->processStringField($column, $field, $rules, $messages, $prepareStatements, $messagePrefix);
                break;
            case 'integer':
            case 'smallint':
            case 'bigint':
                $this->processIntegerField($column, $field, $rules, $messages, $prepareStatements, $messagePrefix, $foreignKeys);
                break;
            case 'float':
            case 'double':
            case 'decimal':
                $this->processNumericField($field, $rules, $messages, $prepareStatements, $messagePrefix);
                break;
            case 'boolean':
                $rules[] = 'boolean';
                $messages["$field.boolean"] = "$messagePrefix deve ser verdadeiro ou falso.";
                $prepareStatements[] = "\$this->merge(['$field' => filter_var(\$this->input('$field'), FILTER_VALIDATE_BOOLEAN)]);";
                break;
            case 'date':
                $rules[] = 'date';
                $messages["$field.date"] = "$messagePrefix deve ser uma data válida.";
                $prepareStatements[] = "\$this->merge(['$field' => date('Y-m-d', strtotime(\$this->input('$field')))]);";
                break;
            case 'datetime':
                $rules[] = 'date_format:Y-m-d H:i:s';
                $messages["$field.date_format"] = "$messagePrefix deve estar no formato AAAA-MM-DD HH:MM:SS.";
                $prepareStatements[] = "\$this->merge(['$field' => date('Y-m-d H:i:s', strtotime(\$this->input('$field')))]);";
                break;
        }
    }

    private function processStringField(array $column, string $field, array &$rules, array &$messages, array &$prepareStatements, string $messagePrefix): void
    {
        $rules[] = 'string';
        $messages["$field.string"] = "$messagePrefix deve ser uma string.";
        preg_match('/\d+/', $column['type'], $matches);

        if (isset($matches[0])) {
            $rules[] = "max:{$matches[0]}";
            $messages["$field.max"] = "$messagePrefix não pode ter mais que {$matches[0]} caracteres.";
        }

        $prepareStatements[] = "\$this->merge(['$field' => trim(\$this->input('$field'))]);";
    }

    private function processIntegerField(array $column, string $field, array &$rules, array &$messages, array &$prepareStatements, string $messagePrefix, array $foreignKeys): void
    {
        $rules[] = 'integer';
        $messages["$field.integer"] = "$messagePrefix deve ser um número inteiro.";
        $prepareStatements[] = "\$this->merge(['$field' => (int) \$this->input('$field')]);";

        $foreignKey = collect($foreignKeys)->firstWhere('columns.0', $field);

        if ($foreignKey) {
            $rules[] = "exists:{$foreignKey['foreign_table']},{$foreignKey['foreign_columns'][0]}";
            $messages["$field.exists"] = "$messagePrefix deve existir na tabela {$foreignKey['foreign_table']}.";
        }
    }

    private function processNumericField(string $field, array &$rules, array &$messages, array &$prepareStatements, string $messagePrefix): void
    {
        $rules[] = 'numeric';
        $messages["$field.numeric"] = "$messagePrefix deve ser um número.";
        $prepareStatements[] = "\$this->merge(['$field' => (float) \$this->input('$field')]);";
    }

    private function createRequestFile(string $modelName, array $rules, array $messages, array $prepareStatements, string $requestPath): void
    {
        $content = $this->buildRequestContent($modelName, $rules, $messages, $prepareStatements);

        if (!File::exists($requestPath)) {
            File::makeDirectory($requestPath, 0755, true);
        }

        File::put($requestPath, $content);
        $this->info("Request criado: {$requestPath}/NovoRequest.php");
    }

    private function buildRequestContent(string $modelName, array $rules, array $messages, array $prepareStatements): string
    {
        return <<<PHP
<?php

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
}
PHP;
    }
}
