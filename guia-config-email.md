# 📧 Guia de Configuração de Email

## Sistema de Envio de Credenciais

O sistema envia automaticamente as credenciais (usuário e senha) por email quando o pagamento é aprovado.

---

## 🎯 O que o Sistema Faz Automaticamente:

1. ✅ Cliente realiza pagamento
2. ✅ Webhook recebe confirmação da InfinityPay
3. ✅ Sistema cria usuário no MikroTik
4. ✅ **Email é enviado automaticamente** com:
   - Nome do plano contratado
   - Usuário gerado
   - Senha gerada
   - Data de validade
   - Instruções de uso

---

## ⚙️ Configurações Necessárias

### **Opção 1: XAMPP + Gmail (Recomendado para testes)**

#### 1.1 Configurar o `sendmail` no XAMPP

Edite o arquivo `C:\xampp\sendmail\sendmail.ini`:

```ini
[sendmail]
smtp_server=smtp.gmail.com
smtp_port=587
error_logfile=error.log
debug_logfile=debug.log
auth_username=seu.email@gmail.com
auth_password=sua_senha_de_aplicativo
force_sender=seu.email@gmail.com
```

**IMPORTANTE**: Use "Senha de Aplicativo" do Gmail, não a senha normal!

Como gerar senha de aplicativo no Gmail:
1. Acesse: https://myaccount.google.com/security
2. Ative "Verificação em 2 etapas"
3. Vá em "Senhas de app"
4. Gere uma senha para "Mail" / "Windows Computer"
5. Use essa senha no `sendmail.ini`

#### 1.2 Configurar o `php.ini`

Edite `C:\xampp\php\php.ini`:

```ini
[mail function]
SMTP=smtp.gmail.com
smtp_port=587
sendmail_from=seu.email@gmail.com
sendmail_path="\"C:\xampp\sendmail\sendmail.exe\" -t"
```

#### 1.3 Reiniciar Apache

Reinicie o Apache no painel do XAMPP.

---

### **Opção 2: Servidor Linux + Gmail**

Instale o `msmtp`:

```bash
sudo apt-get install msmtp msmtp-mta
```

Crie o arquivo `~/.msmtprc`:

```bash
# Gmail SMTP
account default
host smtp.gmail.com
port 587
from seu.email@gmail.com
auth on
user seu.email@gmail.com
password sua_senha_de_aplicativo
tls on
tls_starttls on
tls_trust_file /etc/ssl/certs/ca-certificates.crt
logfile ~/.msmtp.log
```

Configure permissões:

```bash
chmod 600 ~/.msmtprc
```

Edite o `php.ini`:

```ini
sendmail_path = "/usr/bin/msmtp -t"
```

---

### **Opção 3: Servidor de Produção (SendGrid, Mailgun, etc.)**

Para produção, recomendo usar serviço profissional:

#### **SendGrid (100 emails/dia grátis)**

1. Crie conta em: https://sendgrid.com/
2. Gere API Key
3. Instale biblioteca PHP:

```bash
composer require sendgrid/sendgrid
```

4. Modifique a função `sendCredentialsEmail()` no `config.php`:

```php
function sendCredentialsEmail($email, $name, $username, $password, $planName, $expiresAt) {
    require 'vendor/autoload.php';
    
    $sendgrid = new \SendGrid('SUA_API_KEY_AQUI');
    
    $emailObj = new \SendGrid\Mail\Mail();
    $emailObj->setFrom("noreply@wifibarato.com", "WiFi Barato");
    $emailObj->setSubject("Suas credenciais de acesso");
    $emailObj->addTo($email, $name);
    
    $htmlContent = "
    <html>
    <body>
        <h1>Olá, $name!</h1>
        <p>Seu pagamento foi aprovado!</p>
        <p><strong>Usuário:</strong> $username</p>
        <p><strong>Senha:</strong> $password</p>
        <p><strong>Plano:</strong> $planName</p>
        <p><strong>Válido até:</strong> " . date('d/m/Y H:i', strtotime($expiresAt)) . "</p>
    </body>
    </html>
    ";
    
    $emailObj->addContent("text/html", $htmlContent);
    
    try {
        $response = $sendgrid->send($emailObj);
        logEvent('email_sent', "Email enviado para: $email");
        return true;
    } catch (Exception $e) {
        logEvent('email_error', "Erro: " . $e->getMessage());
        return false;
    }
}
```

---

## 🧪 Testar Envio de Email

Crie um arquivo `test_email.php` na raiz do projeto:

```php
<?php
require_once 'config.php';

$testEmail = "seu.email@teste.com"; // MUDE AQUI
$result = sendCredentialsEmail(
    $testEmail,
    "João Teste",
    "wifi_123456",
    "senha123",
    "Plano 2 Horas",
    date('Y-m-d H:i:s', time() + 7200)
);

if ($result) {
    echo "✅ Email enviado com sucesso para $testEmail!<br>";
    echo "Verifique sua caixa de entrada (e spam).";
} else {
    echo "❌ Erro ao enviar email.<br>";
    echo "Verifique os logs do sistema.";
}
?>
```

Acesse: `http://localhost/hotspot/test_email.php`

---

## 🔍 Verificar Logs de Email

### Ver se emails foram enviados:

```sql
SELECT * FROM logs WHERE log_type LIKE 'email%' ORDER BY created_at DESC LIMIT 20;
```

### Logs do SendMail (XAMPP):

Verifique: `C:\xampp\sendmail\error.log`

---

## 📱 Fluxo Completo do Sistema

```
1. Cliente paga via InfinityPay
   ↓
2. InfinityPay envia webhook para: webhook_infinitypay.php
   ↓
3. Sistema valida pagamento (status: paid/approved)
   ↓
4. Sistema cria usuário no banco de dados
   ↓
5. Sistema cria usuário no MikroTik via API
   ↓
6. Sistema envia email com credenciais ✉️
   ↓
7. Cliente recebe email e pode conectar
   ↓
8. Cliente é redirecionado para: payment_success.php
   ↓
9. Página mostra usuário e senha na tela
```

---

## ⚠️ Problemas Comuns

### Problema: "Could not instantiate mail function"

**Solução:**
- Verifique se `sendmail_path` está configurado no `php.ini`
- Reinicie o Apache

### Problema: Emails indo para SPAM

**Solução:**
- Use domínio próprio (não Gmail)
- Configure SPF, DKIM e DMARC
- Use serviço profissional (SendGrid, Mailgun)

### Problema: Gmail bloqueando envio

**Solução:**
- Use "Senha de Aplicativo"
- Ative "Acesso a apps menos seguros" (não recomendado)
- Migre para SendGrid/Mailgun

---

## ✅ Checklist de Configuração

- [ ] `sendmail.ini` configurado (XAMPP)
- [ ] `php.ini` configurado
- [ ] Senha de aplicativo Gmail gerada
- [ ] Apache reiniciado
- [ ] Teste de envio realizado (`test_email.php`)
- [ ] Email recebido com sucesso
- [ ] Logs verificados sem erros
- [ ] Configuração de suporte atualizada no banco:

```sql
UPDATE settings SET setting_value = 'seu.email@dominio.com' WHERE setting_key = 'support_email';
```

---

## 🚀 Melhorias Futuras

1. **Template HTML profissional** com logo
2. **Anexar QR Code** com dados de acesso
3. **Email de lembrete** antes do plano expirar
4. **Email de renovação**
5. **Notificações por WhatsApp** (Twilio/WATI)

---

**✉️ Com isso, seu sistema está completo e enviando credenciais automaticamente por email!**