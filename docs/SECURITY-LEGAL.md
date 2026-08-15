# Segurança, integrações e aspectos jurídicos

## Controles implementados

- Senhas com hash nativo do Laravel; sessão, CSRF e regeneração no login.
- Middleware de papéis e validação de posse no portal do cliente.
- Validação de tipo e tamanho para documentos e imagens.
- OTP armazenado como hash, com expiração, limite de tentativas e uso único.
- Trilha de assinatura com hash de evidência, IP, user-agent e horário.
- OCR com limiar mínimo e confirmação humana obrigatória.
- Operações financeiras críticas protegidas por índices únicos e transações.

## Antes da produção

- Adotar consentimento e política de privacidade adequados à LGPD, definir retenção e atender solicitações dos titulares.
- Criptografar documentos/fotos em repouso, restringir acesso, registrar auditoria e preferir object storage privado.
- Implantar rate limiting reforçado para login, OTP, candidatura, OCR e geração de Pix.
- Validar telefone/CPF, adicionar MFA para gestão e política de senhas/rotação de credenciais.
- Configurar HTTPS, headers de segurança, backups criptografados, gestão de segredos e plano de incidentes.
- Submeter contrato, multas, juros, regras de cobrança, privacidade e assinatura eletrônica à assessoria jurídica.

## Multa e juros

Os percentuais de `.env` são parâmetros contratuais, não uma afirmação de limite legal universal. A Lei do Inquilinato disciplina deveres do locatário e encargos da locação; o Código Civil trata de cláusula penal e juros legais. O limite de 2% do CDC pertence ao contexto de relações de consumo e sua aplicação concreta à locação não deve ser presumida automaticamente.

Fontes oficiais para revisão jurídica:

- [Lei 8.245/1991 — Lei do Inquilinato](https://www.planalto.gov.br/ccivil_03/leis/l8245compilado.htm)
- [Lei 10.406/2002 — Código Civil](https://www.planalto.gov.br/ccivil_03/leis/2002/l10406compilada.htm)
- [Lei 8.078/1990 — Código de Defesa do Consumidor](https://www.planalto.gov.br/ccivil_03/leis/l8078compilado.htm)

## Pix

A implementação atual gera um BR Code EMV local (“Pix copia e cola”), `txid` e expiração de 30 minutos no AlugaPro. Ela não cria uma cobrança dinâmica registrada no banco do PSP e não recebe confirmação automática de pagamento.

Para produção, integre a API Pix do banco/PSP da imobiliária, persistindo `provider_reference`, QR Code dinâmico e webhook assinado. O Banco Central opera a infraestrutura e publica padrões; a aplicação comercial normalmente se conecta por uma instituição participante/PSP, não por uma API pública federal de cobrança ao lojista.

## WhatsApp

`WhatsAppService` é um adaptador HTTP. Sem `WHATSAPP_API_URL` e token, as mensagens são simuladas e auditadas. Ao contratar um provedor, adapte o corpo da requisição, trate webhooks, templates aprovados, opt-out e idempotência.

## Assinatura eletrônica

O fluxo OTP fornece aceite eletrônico e evidências técnicas, mas não equivale automaticamente a uma assinatura qualificada ICP-Brasil. O nível de assinatura necessário depende do documento, risco e orientação jurídica. Para maior força probatória, integre um provedor de assinatura, carimbo do tempo e relatório de evidências verificável.
