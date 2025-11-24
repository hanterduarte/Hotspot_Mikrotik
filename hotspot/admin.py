from django.contrib import admin
from .models import Plan, Customer, Transaction, HotspotUser, Setting, Log

admin.site.register(Plan)
admin.site.register(Customer)
admin.site.register(Transaction)
admin.site.register(HotspotUser)
admin.site.register(Setting)
admin.site.register(Log)
