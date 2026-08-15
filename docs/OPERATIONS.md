# Operação e implantação

## Serviços

| Serviço | Responsabilidade |
|---|---|
| `web` | Nginx, porta pública 8080 |
| `app` | PHP-FPM/Laravel e migrações no início |
| `worker` | Processamento assíncrono de filas |
| `scheduler` | Comandos agendados do Laravel |
| `ocr` | Leitura de visor por FastAPI |
| `db` | PostgreSQL com volume persistente |

## Rotinas automáticas

- Todo dia 1 às 00:05: `billing:generate` cria os aluguéis do mês.
- Todos os dias às 09:00: `billing:remind` registra/envia lembretes e avisos de atraso.
- As rotinas usam `withoutOverlapping` para reduzir duplicidade concorrente.

## Checklist de VPS

1. Configure DNS, firewall e proxy HTTPS; não publique PostgreSQL nem OCR.
2. Copie `.env.example` para `.env`, gere `APP_KEY` e use `APP_ENV=production`, `APP_DEBUG=false`.
3. Defina senhas fortes, credenciais WhatsApp e parâmetros contratuais aprovados.
4. Execute `docker compose up -d --build` e `docker compose exec app php artisan db:seed --class=AdminUserSeeder --force`.
5. Troque imediatamente a senha inicial do administrador.
6. Configure backup criptografado do volume PostgreSQL e teste a restauração.
7. Monitore saúde, logs, espaço em disco, fila e expiração de certificados.

## Backup e restauração

Exemplo de backup lógico, executado em ambiente autorizado:

```powershell
docker compose exec -T db pg_dump -U alugapro -d alugapro -Fc > alugapro.dump
```

Antes da restauração, valide o destino e mantenha uma cópia do banco atual. O projeto não automatiza exclusão de backups.

## Observabilidade

Use `docker compose ps` para saúde e `docker compose logs -f` para diagnóstico. Em produção, encaminhe logs para um agregador e crie alertas para:

- falhas de migração ou inicialização;
- fila acumulada;
- falhas recorrentes de WhatsApp/OCR;
- erros HTTP 5xx;
- pouco espaço no volume do banco.

## Atualização

Faça backup, construa as novas imagens e aplique migrações compatíveis antes de remover a versão anterior. Para disponibilidade contínua, adapte o Compose para implantação blue/green ou use um orquestrador.
