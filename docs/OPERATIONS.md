# Operação e implantação

## Serviços

| Serviço | Responsabilidade |
|---|---|
| `web` | Nginx, porta pública 8080 |
| `app` | PHP-FPM/Laravel e migrações no início |
| `worker` | Processamento assíncrono de filas |
| `scheduler` | Comandos agendados do Laravel |
| `ocr` | Leitura de visor por FastAPI |
| `wppconnect` | Sessão do WhatsApp, QR Code e envio de mensagens/imagens |
| `db` | PostgreSQL com volume persistente |

## Rotinas automáticas

- No último dia de cada mês às 23:55: `billing:generate --next` cria somente as cobranças de aluguel ausentes do mês seguinte.
- Todos os dias às 09:00: `billing:remind` registra/envia lembretes e avisos de atraso.
- A cada cinco minutos: `mia:dispatch-pending` recupera registros da Mia que ficaram pendentes sem um job ativo.
- As rotinas usam `withoutOverlapping` para reduzir duplicidade concorrente.

## Integração com a Mia

Configure a chave do AlugaPro na administração da Mia e copie para o `.env` o token e o **ID da API** do cliente. A integração nasce desabilitada e só deve ser ativada depois de preencher os valores:

```dotenv
MIA_ENABLED=true
MIA_API_URL=https://jbmj.io/mia
MIA_API_TOKEN=chave_cadastrada_para_o_alugapro
MIA_CLIENT_ID=42
MIA_PROPERTY_GROUP_ID=
MIA_PROPERTY_GROUP_NAME="Melo Jr"
```

Prefira `MIA_PROPERTY_GROUP_ID`, porque ele não muda quando o grupo é renomeado. Se o ID ficar vazio, o nome é comparado sem diferenciar maiúsculas/minúsculas e aceitando o prefixo “Grupo”; assim, `Melo Jr` também identifica `Grupo Melo Jr`.

Depois de alterar o ambiente, recrie/reinicie `app`, `worker` e `scheduler` para renovar o cache de configuração. Novas baixas positivas de aluguel ou energia solar do grupo selecionado passam a criar um registro em `mia_receipts`. Baixas sem recebimento (`waiver`) e cobranças de outros grupos não são enviadas.

O worker usa `alugapro:charge:{id}` como `external_id`, preserva o corpo original e trata respostas `200` e `201` como sucesso. Após timeout ou erro `500`, consulta o recebimento antes de repetir. Respostas permanentes ficam com status `failed` e geram log sem token nem corpo financeiro. Corrija a causa e reenfileire o registro local indicado no log:

```powershell
docker compose exec app php artisan mia:retry-receipt 123
```

Não use esse comando para “corrigir” um `409`: investigue na Mia a divergência do lançamento já existente. A API v1 da Mia não oferece edição, exclusão ou estorno; portanto, reabrir no AlugaPro uma cobrança já confirmada na Mia exige conciliação operacional no sistema financeiro.

## Checklist de VPS

1. Configure DNS, firewall e proxy HTTPS; não publique PostgreSQL nem OCR.
2. Copie `.env.example` para `.env`, gere `APP_KEY` e use `APP_ENV=production`, `APP_DEBUG=false`.
3. Defina senhas fortes, gere uma `WPP_CONNECT_SECRET_KEY` longa e aleatória e revise os parâmetros contratuais.
4. Execute `docker compose up -d --build` e `docker compose exec app php artisan db:seed --class=AdminUserSeeder --force`.
5. Troque imediatamente a senha inicial do administrador.
6. Configure backup criptografado dos volumes PostgreSQL e WPPConnect e teste a restauração.
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
- recebimentos da Mia com status `failed` ou pendentes por tempo excessivo;
- falhas recorrentes de WhatsApp/OCR;
- erros HTTP 5xx;
- pouco espaço no volume do banco.

## Atualização

Faça backup, construa as novas imagens e aplique migrações compatíveis antes de remover a versão anterior. Para disponibilidade contínua, adapte o Compose para implantação blue/green ou use um orquestrador.
