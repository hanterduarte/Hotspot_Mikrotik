from django.db import models

class Plan(models.Model):
    name = models.CharField(max_length=100)
    duration = models.CharField(max_length=50)
    duration_seconds = models.IntegerField()
    price = models.DecimalField(max_digits=10, decimal_places=2)
    description = models.TextField(blank=True, null=True)
    active = models.BooleanField(default=True)
    created_at = models.DateTimeField(auto_now_add=True)

    def __str__(self):
        return self.name

class Customer(models.Model):
    name = models.CharField(max_length=255)
    email = models.EmailField(unique=True)
    phone = models.CharField(max_length=20, blank=True, null=True)
    cpf = models.CharField(max_length=14, blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)

    def __str__(self):
        return self.name

class Transaction(models.Model):
    customer = models.ForeignKey(Customer, on_delete=models.CASCADE)
    plan = models.ForeignKey(Plan, on_delete=models.CASCADE)
    payment_id = models.CharField(max_length=255, blank=True, null=True)
    payment_method = models.CharField(max_length=50, blank=True, null=True)
    payment_status = models.CharField(max_length=50, default='pending')
    amount = models.DecimalField(max_digits=10, decimal_places=2)
    gateway = models.CharField(max_length=50)
    gateway_response = models.TextField(blank=True, null=True)
    paid_at = models.DateTimeField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return f"Transaction {self.id} for {self.customer}"

class HotspotUser(models.Model):
    transaction = models.OneToOneField(Transaction, on_delete=models.CASCADE)
    customer = models.ForeignKey(Customer, on_delete=models.CASCADE)
    username = models.CharField(max_length=100, unique=True)
    password = models.CharField(max_length=255)
    plan = models.ForeignKey(Plan, on_delete=models.CASCADE)
    expires_at = models.DateTimeField()
    active = models.BooleanField(default=True)
    mikrotik_synced = models.BooleanField(default=False)
    created_at = models.DateTimeField(auto_now_add=True)
    used_at = models.DateTimeField(blank=True, null=True)

    def __str__(self):
        return self.username

class Setting(models.Model):
    setting_key = models.CharField(max_length=100, unique=True)
    setting_value = models.TextField(blank=True, null=True)
    description = models.TextField(blank=True, null=True)
    updated_at = models.DateTimeField(auto_now=True)

    def __str__(self):
        return self.setting_key

class Log(models.Model):
    log_type = models.CharField(max_length=50)
    log_message = models.TextField()
    related_id = models.IntegerField(blank=True, null=True)
    created_at = models.DateTimeField(auto_now_add=True)

    def __str__(self):
        return f"[{self.created_at}] {self.log_type}: {self.log_message}"
