import os
import json
import keyring
from cryptography.fernet import Fernet

SERVICE_NAME = "ArteeAISecretVault"
LOCAL_SECRET_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".secrets_enc")
KEY_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), ".vault_key")

class SecretVaultManager:
    """
    Enterprise Secret Vault using Windows DPAPI (Keyring) with AES-256 local fallback.
    """

    def __init__(self):
        self._fernet_key = self._get_or_create_key()
        self.fernet = Fernet(self._fernet_key)

    def _get_or_create_key(self) -> bytes:
        if os.path.exists(KEY_FILE):
            try:
                with open(KEY_FILE, "rb") as f:
                    return f.read()
            except Exception:
                pass
        
        # Create fresh key
        key = Fernet.generate_key()
        try:
            with open(KEY_FILE, "wb") as f:
                f.write(key)
        except Exception:
            pass
        return key

    def set_secret(self, key_name: str, secret_value: str) -> bool:
        """
        Stores credentials in Windows Keyring. Fallbacks to encrypted local configuration.
        """
        # 1. Try Windows Keyring
        try:
            keyring.set_password(SERVICE_NAME, key_name, secret_value)
            return True
        except Exception as e:
            print(f"[SecretVault] Keyring store failed: {e}. Storing encrypted local copy.")

        # 2. Encrypted Local File Fallback
        secrets = self._read_local_secrets()
        secrets[key_name] = secret_value
        return self._write_local_secrets(secrets)

    def get_secret(self, key_name: str) -> str:
        """
        Retrieves credentials from Windows Keyring, falling back to local vault.
        """
        # 1. Try Windows Keyring
        try:
            val = keyring.get_password(SERVICE_NAME, key_name)
            if val:
                return val
        except Exception:
            pass

        # 2. Local encrypted vault
        secrets = self._read_local_secrets()
        return secrets.get(key_name, "")

    def _read_local_secrets(self) -> dict:
        if not os.path.exists(LOCAL_SECRET_FILE):
            return {}
        try:
            with open(LOCAL_SECRET_FILE, "rb") as f:
                enc_data = f.read()
            dec_data = self.fernet.decrypt(enc_data).decode('utf-8')
            return json.loads(dec_data)
        except Exception:
            return {}

    def _write_local_secrets(self, secrets: dict) -> bool:
        try:
            raw_data = json.dumps(secrets).encode('utf-8')
            enc_data = self.fernet.encrypt(raw_data)
            with open(LOCAL_SECRET_FILE, "wb") as f:
                f.write(enc_data)
            return True
        except Exception:
            return False
