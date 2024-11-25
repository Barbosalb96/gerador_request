
# Request Generator Package

Este pacote permite gerar automaticamente classes de **Form Request** no Laravel com base em um **Model**, criando validações e mensagens de erro automaticamente para os campos da tabela.

## Requisitos

- PHP >= 8.0
- Laravel >= 8.x

---

## Instalação

1. **Adicione o pacote no projeto usando o Composer**  
   Execute o comando abaixo para instalar o pacote:

   ```bash
   composer require barbosalb96/request
   ```

2. **Configure o Service Provider**  
   Certifique-se de que o **Service Provider** foi registrado no arquivo `config/app.php`. Caso não esteja, adicione manualmente:

   ```php
   'providers' => [
       ...
       Lucas\GenerateRequestServiceProvider::class,
   ],
   ```

3. **Publicação do comando (se necessário)**  
   O pacote registra automaticamente o comando no ambiente do console Laravel. Não é necessária publicação de arquivos de configuração.

---

## Uso

### Comando Básico

Para gerar uma classe de **Form Request**, execute o seguinte comando no terminal:

```bash
php artisan make:request-from-model {ModelName}
```

Substitua `{ModelName}` pelo nome da classe do **Model**. Por exemplo:

```bash
php artisan make:request-from-model User
```

Este comando irá:

- Ler a tabela associada ao Model.
- Criar um arquivo de request em `app/Http/Requests/{ModelName}Request.php`.

---

## Funcionalidades

- **Geração Automática de Regras de Validação:**  
  As regras são geradas com base nos campos do banco de dados (tipos de dados e restrições como `nullable` ou `required`).

- **Mensagens Personalizadas:**  
  Mensagens de erro legíveis são geradas automaticamente para cada campo e tipo de validação.

- **Preparação para Validação:**  
  Os campos são automaticamente preparados (ex.: remoção de espaços, conversão de tipos) antes da validação.

---

## Estrutura do Request Gerado

Exemplo de um request gerado para um modelo `User`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'age' => 'nullable|integer',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Name é obrigatório.',
            'email.required' => 'Email é obrigatório.',
            'email.email' => 'Email deve ser um endereço válido.',
            'age.integer' => 'Age deve ser um número inteiro.',
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'name' => trim($this->input('name')),
            'email' => trim($this->input('email')),
            'age' => is_null($this->input('age')) ? null : (int) $this->input('age'),
        ]);
    }
}
```

---

## Contribuição

Caso encontre algum problema ou queira contribuir com melhorias, envie uma issue ou faça um pull request no repositório do projeto.

---

## Licença

Este pacote está licenciado sob a [Licença MIT](LICENSE).
