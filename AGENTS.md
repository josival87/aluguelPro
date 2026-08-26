# Instruções do projeto AlugaPro

## Execução dos testes PHP

Neste ambiente Windows, execute os testes PHP diretamente em um contêiner temporário. Não faça tentativas preliminares com outros runtimes.

### Comando obrigatório

Execute a partir da raiz do repositório (`C:\AlugaPro`):

```powershell
docker compose run --rm --no-deps -v "${PWD}:/var/www/html" -w /var/www/html --entrypoint php app artisan test
```

Para executar somente testes específicos, acrescente os caminhos ao final:

```powershell
docker compose run --rm --no-deps -v "${PWD}:/var/www/html" -w /var/www/html --entrypoint php app artisan test tests/Feature/NomeDoTeste.php
```

### Motivo

- O PHP instalado no host não possui o driver `pdo_sqlite`, exigido pelos testes com SQLite em memória.
- A imagem normal do serviço `app` possui `pdo_sqlite`, mas foi construída com `composer install --no-dev`; portanto, o contêiner permanente não contém PHPUnit nem o comando `artisan test`.
- O comando obrigatório combina o PHP e o `pdo_sqlite` da imagem com o código e as dependências de desenvolvimento do workspace montado em `/var/www/html`.
- `--no-deps` e o ambiente de teste com SQLite em memória evitam usar o PostgreSQL da aplicação.

### Regras para os agentes

- Não tente primeiro `php artisan test` no host: ele falhará com `could not find driver`.
- Não tente `docker compose exec app php artisan test`: ele falhará porque a imagem permanente não possui as dependências de desenvolvimento e também pode conter uma cópia antiga do código.
- Não reconstrua o serviço `app`, não execute migrações e não altere o banco PostgreSQL apenas para rodar testes.
- Se o acesso à API do Docker for negado pelo sandbox, repita imediatamente o mesmo comando obrigatório solicitando a permissão necessária; não procure outro runtime.
- Use a execução direcionada aos testes relacionados durante o desenvolvimento e a suíte completa quando a abrangência da mudança justificar.
