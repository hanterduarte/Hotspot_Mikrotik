# 🚀 Sistema de Hotspot Automático - Guia de Instalação

Sistema completo de pagamento automático para hotspot MikroTik com integração ao Mercado Pago.

## 📋 Requisitos

- **Servidor Web**: Apache com PHP 7.4 ou superior
- **Banco de Dados**: MySQL 5.7 ou superior
- **MikroTik RouterOS**: Versão 6.x ou superior com API habilitada
- **Conta Mercado Pago**: Para processar pagamentos

## 📁 Estrutura de Arquivos

```
/hotspot/
├── config.php
├── MikrotikAPI.php
├── MercadoPago.php
├── index.php
├── process_payment.php
├── webhook_mercadopago.php
├── pix_payment.php
├── check_payment_status.php
├── payment_success.php
├── payment_failure.php
├── payment_pending.php
└── img/
    └── wifi-barato-logo.png
```

## 🔧 Passo 1: Configurar o Banco de Dados

### 1.1 Criar o banco de dados

Abra o phpMyAdmin ou MySQL e execute o script SQL fornecido no arquivo `database.sql`.

```sql
-- O script cria:
-- - Banco de dados: hotspot_system
-- - Tabelas: plans, customers, transactions, hotspot_users, settings, logs
-- - Dados iniciais dos planos
```

### 1.2 Verificar a criação

```sql
USE hotspot_system;
SHOW TABLES;
```

Você deve ver 6 tabelas criadas.

## ⚙️ Passo 2: Configurar o Sistema

### 2.1 Editar config.php

Abra `config.php` e ajuste as configurações:

```php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'hotspot_system');
define('DB_USER', 'root');          // Seu usuário MySQL
define('DB_PASS', '');              // Sua senha MySQL

// URL base do sistema
define('BASE_URL', 'http://seudominio.com/hotspot');
```

### 2.2 Copiar arquivos

Faça upload de todos os arquivos para a pasta `/hotspot/` no seu servidor.

### 2.3 Adicionar logo

Coloque o arquivo `wifi-barato-logo.png` na pasta `/hotspot/img/`

## 🔐 Passo 3: Configurar o MikroTik

### 3.1 Habilitar a API

No WinBox ou terminal do MikroTik:

```
/ip service
set api address=0.0.0.0/0 disabled=no port=8728
```

### 3.2 Criar perfil de hotspot (se não existir)

```
/ip hotspot profile
add name=default shared-users=1 use-radius=no
```

### 3.3 Testar conexão

No terminal do servidor, teste a conexão:

```bash
telnet IP_DO_MIKROTIK 8728
```

Se conectar, a API está funcionando!

### 3.4 Atualizar configurações no banco

```sql
UPDATE settings SET setting_value = '192.168.88.1' WHERE setting_key = 'mikrotik_host';
UPDATE settings SET setting_value = 'admin' WHERE setting_key = 'mikrotik_user';
UPDATE settings SET setting_value = 'sua_senha' WHERE setting_key = 'mikrotik_password';
```

## 💳 Passo 4: Configurar Mercado Pago

### 4.1 Criar conta de desenvolvedor

1. Acesse: https://www.mercadopago.com.br/developers
2. Faça login ou crie uma conta
3. Vá em "Suas integrações" → "Criar aplicação"

### 4.2 Obter credenciais

1. Na aplicação criada, copie:
   - **Public Key** (começa com APP_USR)
   - **Access Token** (começa com APP_USR)

### 4.3 Configurar no sistema

```sql
UPDATE settings SET setting_value = 'SEU_ACCESS_TOKEN' WHERE setting_key = 'mercadopago_access_token';
UPDATE settings SET setting_value = 'SUA_PUBLIC_KEY' WHERE setting_key = 'mercadopago_public_key';
```

### 4.4 Configurar webhook

1. No painel do Mercado Pago, vá em "Webhooks"
2. Adicione a URL: `https://wifibarato.maiscoresed.com.br/hotspot/webhook_mercadopago.php`
3. Selecione o evento: "Payments"
4. Salve

**IMPORTANTE**: O webhook só funciona com HTTPS! Se estiver testando localmente, use ngrok ou similar.

## 🧪 Passo 5: Testar o Sistema

### 5.1 Modo de teste (Sandbox)

Para testar sem cobrar de verdade:

1. No Mercado Pago, use as credenciais de **Teste**
2. Use cartões de teste: https://www.mercadopago.com.br/developers/pt/docs/checkout-pro/additional-content/test-cards

### 5.2 Fluxo de teste

1. Acesse: `http://seudominio.com/hotspot/`
2. Selecione um plano
3. Preencha o formulário com dados de teste
4. Escolha PIX ou Cartão
5. Complete o pagamento
6. Aguarde a criação automática do usuário

### 5.3 Verificar logs

```sql
SELECT * FROM logs ORDER BY created_at DESC LIMIT 20;
```

Verifique se não há erros.

### 5.4 Verificar usuário criado

```sql
SELECT * FROM hotspot_users ORDER BY created_at DESC;
```

No MikroTik:

```
/ip hotspot user print
```

## 📧 Passo 6: Configurar Email (Opcional)

Para enviar credenciais por email, configure o PHP para enviar emails.

### 6.1 Usando SMTP (recomendado)

Instale PHPMailer ou configure o `php.ini`:

```ini
[mail function]
SMTP = smtp.gmail.com
smtp_port = 587
sendmail_from = seu@email.com
sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"
```

### 6.2 Testar envio de email

Crie um arquivo `test_email.php`:

```php
<?php
$to = "seu@email.com";
$subject = "Teste";
$message = "Email de teste";
$headers = "From: sistema@seudominio.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Email enviado!";
} else {
    echo "Erro ao enviar email";
}
?>
```

## 🛠️ Passo 7: Personalização

### 7.1 Ajustar planos

```sql
-- Adicionar novo plano
INSERT INTO plans (name, duration, duration_seconds, price, description) 
VALUES ('1 Hora', '1h', 3600, 5.00, 'Plano de 1 hora');

-- Desativar plano
UPDATE plans SET active = 0 WHERE id = 1;

-- Alterar preço
UPDATE plans SET price = 20.00 WHERE id = 3;
```

### 7.2 Alterar informações de contato

```sql
UPDATE settings SET setting_value = '(81) 99999-9999' WHERE setting_key = 'support_phone';
UPDATE settings SET setting_value = 'contato@seudominio.com' WHERE setting_key = 'support_email';
```

### 7.3 Personalizar cores da página

Edite o `index.php` e altere as cores no CSS:

```css
/* Alterar gradiente de fundo */
background: linear-gradient(135deg, #SUA_COR_1 0%, #SUA_COR_2 100%);
```

## 🔒 Passo 8: Segurança

### 8.1 Proteger arquivos sensíveis

Crie um arquivo `.htaccess` na raiz:

```apache
# Bloquear acesso direto a arquivos PHP críticos
<FilesMatch "^(config|MikrotikAPI|MercadoPago)\.php$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Permitir apenas POST no webhook
<Files "webhook_mercadopago.php">
    <Limit GET>
        Order allow,deny
        Deny from all
    </Limit>
</Files>
```

### 8.2 Usar HTTPS

**OBRIGATÓRIO para produção!**

1. Obtenha certificado SSL (Let's Encrypt é grátis)
2. Configure no Apache/Nginx
3. Redirecione HTTP para HTTPS

### 8.3 Proteger banco de dados

```sql
-- Criar usuário específico
CREATE USER 'hotspot_user'@'localhost' IDENTIFIED BY 'senha_forte';
GRANT SELECT, INSERT, UPDATE ON hotspot_system.* TO 'hotspot_user'@'localhost';
FLUSH PRIVILEGES;
```

Atualize o `config.php` com as novas credenciais.

## 📊 Passo 9: Monitoramento

### 9.1 Verificar pagamentos pendentes

```sql
SELECT t.*, c.name, c.email, p.name as plan_name
FROM transactions t
JOIN customers c ON t.customer_id = c.id
JOIN plans p ON t.plan_id = p.id
WHERE t.payment_status = 'pending'
ORDER BY t.created_at DESC;
```

### 9.2 Verificar usuários ativos

```sql
SELECT hu.username, c.name, p.name as plan, hu.expires_at, hu.active
FROM hotspot_users hu
JOIN customers c ON hu.customer_id = c.id
JOIN plans p ON hu.plan_id = p.id
WHERE hu.active = 1 AND hu.expires_at > NOW()
ORDER BY hu.created_at DESC;
```

### 9.3 Ver logs de erros

```sql
SELECT * FROM logs WHERE log_type LIKE '%error%' ORDER BY created_at DESC LIMIT 50;
```

## 🐛 Resolução de Problemas

### Problema: "Erro ao conectar ao MikroTik"

**Soluções:**
1. Verifique se a API está habilitada: `/ip service print`
2. Teste a conexão: `telnet IP_MIKROTIK 8728`
3. Verifique firewall: `/ip firewall filter print`
4. Confira usuário e senha no banco de dados

### Problema: "Webhook não recebe notificações"

**Soluções:**
1. Certifique-se de usar HTTPS
2. Verifique se a URL está correta no Mercado Pago
3. Veja os logs: `SELECT * FROM logs WHERE log_type LIKE 'webhook%'`
4. Teste manualmente acessando o webhook

### Problema: "Pagamento aprovado mas usuário não foi criado"

**Soluções:**
1. Verifique os logs: `SELECT * FROM logs ORDER BY created_at DESC`
2. Confira se há erro de conexão com MikroTik
3. Verifique se a transação foi atualizada: `SELECT * FROM transactions WHERE payment_status = 'approved'`
4. Execute o webhook manualmente com o payment_id

### Problema: "Erro ao enviar email"

**Soluções:**
1. Verifique configuração do PHP: `php -i | grep mail`
2. Use SMTP externo (Gmail, SendGrid, etc.)
3. Desabilite envio de email temporariamente
4. Teste com `test_email.php`

## 📱 Passo 10: Integração com Hotspot MikroTik

### 10.1 Substituir página de login padrão

1. Conecte via FTP no MikroTik
2. Navegue até `/hotspot/`
3. Faça backup do `login.html` original
4. **IMPORTANTE**: Mantenha a página de login original do MikroTik funcionando para autenticação

### 10.2 Fluxo recomendado

**Opção 1: Duas páginas separadas**
- `/hotspot/comprar.php` - Página de compra (este sistema)
- `/hotspot/login.html` - Página de login (MikroTik padrão)

**Opção 2: Página única integrada**
- Criar página que detecta se usuário já tem credenciais
- Se não tem, mostra planos
- Se tem, mostra formulário de login

### 10.3 Redirecionar para página de compra

No `/hotspot/login.html` do MikroTik, adicione link:

```html
<p>Não tem acesso? <a href="comprar.php">Compre aqui</a></p>
```

## 🎯 Passo 11: Produção

### 11.1 Checklist antes de colocar no ar

- [ ] Banco de dados criado e configurado
- [ ] Credenciais do Mercado Pago de PRODUÇÃO configuradas
- [ ] SSL/HTTPS configurado
- [ ] Webhook configurado e testado
- [ ] MikroTik conectado e testado
- [ ] Email funcionando
- [ ] Logs sendo gravados corretamente
- [ ] Backup automático configurado
- [ ] Planos com preços corretos
- [ ] Informações de contato atualizadas
- [ ] Logo personalizada adicionada

### 11.2 Alterar para modo produção

```sql
-- Usar credenciais de produção do Mercado Pago
UPDATE settings SET setting_value = 'SEU_ACCESS_TOKEN_PRODUCAO' WHERE setting_key = 'mercadopago_access_token';
UPDATE settings SET setting_value = 'SUA_PUBLIC_KEY_PRODUCAO' WHERE setting_key = 'mercadopago_public_key';
```

### 11.3 Configurar backup automático

Crie script de backup (`backup.sh`):

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u root -p hotspot_system > /backups/hotspot_$DATE.sql
find /backups -name "hotspot_*.sql" -mtime +7 -delete
```

Configure no cron:

```bash
crontab -e
# Adicionar linha:
0 2 * * * /caminho/backup.sh
```

## 📈 Passo 12: Melhorias Futuras

### Recursos que podem ser adicionados:

1. **Painel Administrativo**
   - Visualizar vendas
   - Gerenciar usuários
   - Relatórios financeiros
   - Configurar sistema

2. **Notificações**
   - WhatsApp API
   - SMS
   - Telegram

3. **Múltiplos Gateways**
   - PagSeguro
   - PayPal
   - Stripe

4. **Recursos Avançados**
   - Cupons de desconto
   - Programa de indicação
   - Renovação automática
   - Portal do cliente

## 📞 Suporte

### Logs importantes para análise:

```sql
-- Ver todas as transações
SELECT * FROM transactions ORDER BY created_at DESC;

-- Ver usuários criados hoje
SELECT * FROM hotspot_users WHERE DATE(created_at) = CURDATE();

-- Ver erros recentes
SELECT * FROM logs WHERE log_type LIKE '%error%' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);
```

### Teste manual do webhook:

```bash
curl -X POST http://seudominio.com/hotspot/webhook_mercadopago.php \
  -H "Content-Type: application/json" \
  -d '{"type":"payment","data":{"id":"SEU_PAYMENT_ID"}}'
```

## ✅ Sistema Instalado!

Após seguir todos os passos, seu sistema estará:

- ✅ Recebendo pagamentos automaticamente
- ✅ Criando usuários no MikroTik
- ✅ Enviando credenciais por email
- ✅ Registrando todas as operações em logs
- ✅ Pronto para uso em produção

---

## 🎓 Dicas Importantes

1. **Sempre teste em ambiente de desenvolvimento primeiro**
2. **Mantenha backups regulares do banco de dados**
3. **Monitore os logs diariamente no início**
4. **Use credenciais de teste do Mercado Pago antes de ir para produção**
5. **Configure alertas para erros críticos**
6. **Documente qualquer personalização que fizer**

---

**Desenvolvido para WiFi Barato**
Versão 1.0 - 2024