import routeros_api
from .models import Setting

class MikrotikAPI:
    def __init__(self):
        self.host = Setting.objects.get(setting_key='mikrotik_host').setting_value
        self.port = int(Setting.objects.get(setting_key='mikrotik_port').setting_value)
        self.user = Setting.objects.get(setting_key='mikrotik_user').setting_value
        self.password = Setting.objects.get(setting_key='mikrotik_password').setting_value
        self.api = None
        self.connection = None

    def connect(self):
        if self.connection:
            return True
        try:
            self.connection = routeros_api.RouterOsApiPool(
                self.host,
                username=self.user,
                password=self.password,
                port=self.port,
                plaintext_login=True
            )
            self.api = self.connection.get_api()
            return True
        except routeros_api.exceptions.RouterOsApiConnectionError as e:
            print(f"Error connecting to MikroTik: {e}")
            return False

    def provision_hotspot_user(self, plan, username, password):
        if not self.connect():
            return {'success': False, 'message': 'Failed to connect to MikroTik.'}

        try:
            uptime_limit = f"{plan.duration_seconds}s" if plan.duration_seconds > 0 else ''

            self.api.get_resource('/ip/hotspot/user').add({
                'name': username,
                'password': password,
                'profile': 'default',  # Or get from plan
                'limit-uptime': uptime_limit,
                'comment': f"Plan ID: {plan.id}"
            })
            return {'success': True, 'message': 'User provisioned successfully.'}
        except Exception as e:
            print(f"Error provisioning user: {e}")
            return {'success': False, 'message': str(e)}

    def disconnect(self):
        if self.connection:
            self.connection.disconnect()
            self.connection = None
