from django.urls import path
from .views import PlanListView, CustomerCreateView, PaymentProcessView, InfinityPayWebhookView

urlpatterns = [
    path('plans/', PlanListView.as_view(), name='plan-list'),
    path('customers/', CustomerCreateView.as_view(), name='customer-create'),
    path('process-payment/', PaymentProcessView.as_view(), name='process-payment'),
    path('webhook/infinitypay/', InfinityPayWebhookView.as_view(), name='infinitypay-webhook'),
]
