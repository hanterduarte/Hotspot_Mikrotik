import requests
from .models import Setting

class InfinityPay:
    def __init__(self):
        self.handle = Setting.objects.get(setting_key='infinitepay_handle').setting_value
        self.api_url = 'https://api.infinitepay.io/invoices/public/checkout/links'

    def create_checkout_link(self, plan, customer, transaction_id):
        items = [
            {
                'quantity': 1,
                'price': int(plan.price * 100),
                'description': f'{plan.name} - Acesso Hotspot'
            }
        ]

        order_nsu = str(transaction_id)
        base_url = Setting.objects.get(setting_key='base_url').setting_value
        redirect_url = f"{base_url}/payment_success.php?external_reference={order_nsu}"
        webhook_url = f"{base_url}/webhook_infinitypay.php"

        data = {
            "handle": self.handle,
            "redirect_url": redirect_url,
            "webhook_url": webhook_url,
            "order_nsu": order_nsu,
            "customer": {
                "name": customer.name,
                "email": customer.email,
                "phone_number": customer.phone
            },
            "items": items
        }

        try:
            response = requests.post(self.api_url, json=data)
            response.raise_for_status()  # Raise an exception for bad status codes
            response_data = response.json()
            return {'success': True, 'url': response_data.get('url')}
        except requests.exceptions.RequestException as e:
            print(f"Error creating checkout link: {e}")
            return {'success': False, 'message': str(e)}
