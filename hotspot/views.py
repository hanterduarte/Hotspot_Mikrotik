from django.views.generic import TemplateView
from rest_framework import generics, status
from rest_framework.response import Response
from rest_framework.views import APIView
from .models import Plan, Customer, Transaction, HotspotUser
from .serializers import PlanSerializer, CustomerSerializer, TransactionSerializer
from .infinitypay import InfinityPay
from .mikrotik import MikrotikAPI
import datetime
import random
import string

class ReactAppView(TemplateView):
    template_name = 'index.html'

class PlanListView(generics.ListAPIView):
    queryset = Plan.objects.filter(active=True)
    serializer_class = PlanSerializer

class CustomerCreateView(generics.CreateAPIView):
    queryset = Customer.objects.all()
    serializer_class = CustomerSerializer

class PaymentProcessView(APIView):
    def post(self, request, *args, **kwargs):
        customer_data = request.data.get('customer')
        plan_id = request.data.get('plan_id')

        if not customer_data or not plan_id:
            return Response({'message': 'Customer data and plan ID are required.'}, status=status.HTTP_400_BAD_REQUEST)

        try:
            plan = Plan.objects.get(id=plan_id)
        except Plan.DoesNotExist:
            return Response({'message': 'Plan not found.'}, status=status.HTTP_404_NOT_FOUND)

        customer, created = Customer.objects.get_or_create(
            email=customer_data['email'],
            defaults=customer_data
        )

        transaction = Transaction.objects.create(
            customer=customer,
            plan=plan,
            amount=plan.price,
            gateway='infinitypay'
        )

        infinitypay = InfinityPay()
        result = infinitypay.create_checkout_link(plan, customer, transaction.id)

        if result['success']:
            transaction.payment_id = result['url'].split('/')[-1]
            transaction.save()
            return Response({'redirect_url': result['url']})
        else:
            return Response({'message': result['message']}, status=status.HTTP_500_INTERNAL_SERVER_ERROR)

class InfinityPayWebhookView(APIView):
    def post(self, request, *args, **kwargs):
        data = request.data
        transaction_id = data.get('order_nsu')
        payment_status = data.get('status')

        if not transaction_id or not payment_status:
            return Response(status=status.HTTP_400_BAD_REQUEST)

        try:
            transaction = Transaction.objects.get(id=transaction_id)
        except Transaction.DoesNotExist:
            return Response(status=status.HTTP_404_NOT_FOUND)

        if payment_status == 'PAID' and transaction.payment_status != 'paid':
            transaction.payment_status = 'paid'
            transaction.paid_at = datetime.datetime.now()
            transaction.gateway_response = data
            transaction.save()

            # Create hotspot user
            username = ''.join(random.choices(string.ascii_lowercase + string.digits, k=8))
            password = ''.join(random.choices(string.ascii_lowercase + string.digits, k=8))

            expires_at = datetime.datetime.now() + datetime.timedelta(seconds=transaction.plan.duration_seconds)

            hotspot_user = HotspotUser.objects.create(
                transaction=transaction,
                customer=transaction.customer,
                username=username,
                password=password,
                plan=transaction.plan,
                expires_at=expires_at,
            )

            # Provision user on MikroTik
            mikrotik = MikrotikAPI()
            result = mikrotik.provision_hotspot_user(transaction.plan, username, password)

            if result['success']:
                hotspot_user.mikrotik_synced = True
                hotspot_user.save()

        return Response(status=status.HTTP_200_OK)
