# AlugaPro

Plataforma web responsiva para imobiliárias administrarem imóveis, interessados, clientes, aluguéis, contratos, cobranças, Pix, notificações e energia solar.

## O que está implementado

- Vitrine pública de imóveis residenciais e comerciais, com filtros e candidatura digital.
- Portal do cliente com aluguéis, cobranças, regras, contratos e geração de Pix.
- Administração com indicadores, calendário de cobranças e CRUDs de empresa, grupos, usuários, clientes, características, imóveis, contratos-base e aluguéis.
- Geração idempotente de cobranças mensais, baixa manual, multa e juros contratuais.
- Contratos residencial e comercial iniciais, variáveis `{{nome_cliente}}`, `{{cpf_cliente}}` e demais dados do domínio, editor rico para ajustes finais, versão imutável por aluguel, hash SHA-256, assinatura por código temporário via WhatsApp e trilha de evidências.
- Ficha do aluguel com múltiplos documentos em Base64 no PostgreSQL, incluindo contratos antigos, aditivos, vistorias, comprovantes e outros arquivos, com download protegido e verificação SHA-256.
- Medição solar por foto, OCR separado em FastAPI/OpenCV/Tesseract, confirmação humana e cálculo por diferença de leituras.
- Worker de filas, agendador, PostgreSQL, Nginx e serviços Docker prontos para VPS.

## Execução local

Requisitos: Docker Desktop e Docker Compose.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan db:seed --force
```

Acesse [http://localhost:8080](http://localhost:8080).

Contas de demonstração:

| Área | Login | Senha |
|---|---|---|
| Administração | `admin` | `AlugaPro@2026` |
| Cliente | `cliente@alugapro.local` | `Cliente@2026` |

Troque essas credenciais e `DB_PASSWORD` antes de qualquer publicação.

## Comandos úteis

```powershell
docker compose ps
docker compose logs -f app worker scheduler ocr
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan billing:generate
docker compose exec app php artisan billing:remind
docker compose down
```

Os dados PostgreSQL permanecem no volume `postgres_data`. `docker compose down -v` também apaga esse volume e não deve ser usado sem backup.

## Foto de referência de energia

A imagem fornecida está em `public/images/meter-reference-517.jpeg` e representa o medidor DDS668 do APT102, com leitura válida informada de **517 kWh na segunda linha**. O seeder registra:

- leitura anterior: 400 kWh;
- leitura atual confirmada: 517 kWh;
- consumo: 117 kWh;
- tarifa demonstrativa: R$ 0,95/kWh;
- cobrança de energia: R$ 111,15.

O LCD da foto é escuro e reflexivo. O OCR local não atingiu confiança suficiente nessa amostra e, deliberadamente, o sistema não usa a sugestão fraca para gerar cobrança. O valor digitado/confirmado pelo operador é a fonte final, e a foto, o resultado bruto e o responsável pela confirmação ficam na trilha de auditoria.

## Configurações principais

| Variável | Finalidade |
|---|---|
| `LATE_FEE_PERCENT` | Multa contratual por atraso |
| `MONTHLY_INTEREST_PERCENT` | Juros mensais, rateados por dia |
| `PIX_EXPIRATION_MINUTES` | Validade local de cada código Pix |
| `OTP_EXPIRATION_MINUTES` | Validade do código de assinatura |
| `OCR_MIN_CONFIDENCE` | Confiança mínima para sugerir uma leitura |
| `WHATSAPP_API_*` | Endpoint, token e remetente do provedor WhatsApp |

Sem credenciais WhatsApp, os envios são simulados e gravados em `notification_logs`.

## Documentação

- [Arquitetura](docs/ARCHITECTURE.md)
- [Operação e implantação](docs/OPERATIONS.md)
- [Segurança, integrações e aspectos jurídicos](docs/SECURITY-LEGAL.md)

## Verificação

```powershell
php artisan test --testsuite=Unit
php artisan view:cache
docker compose exec ocr pytest -q
```

O teste OCR marcado como `golden` é opcional (`RUN_OCR_GOLDEN=1`) porque a foto de referência exige confirmação humana e não deve ser artificialmente aceita com baixa confiança.
"# aluguelPro" 
