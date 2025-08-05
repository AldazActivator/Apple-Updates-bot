# 📡 Apple Security & Firmware Telegram Bot

Este bot de Telegram monitorea automáticamente **actualizaciones de seguridad y versiones de firmware de Apple**. Es ideal para usuarios, desarrolladores y técnicos que deseen mantenerse informados sobre cambios importantes en el ecosistema Apple.

## 🚀 Características

- ✅ Comando `/start` para suscribirse desde Telegram
- 🔐 Monitorea el sitio oficial de Apple: [support.apple.com/102774](https://support.apple.com/en-us/102774)
- 📅 Detecta nuevos reconocimientos de seguridad publicados (acknowledgements)
- 📦 Detecta nuevas versiones de:
  - iOS
  - iPadOS
  - macOS
  - bridgeOS
- 📩 Notificaciones automáticas para todos los usuarios suscritos
- 💾 Almacena usuarios y últimos registros de forma local (`.json`)
- ☁️ Se puede ejecutar como webhook o mediante consulta GET (`?status=check`)

## 🧠 ¿Cómo funciona?

El script realiza dos tareas principales:

### 1. Monitoreo de Reconocimientos de Seguridad

- Extrae los meses más recientes y las personas reconocidas desde la [página de Apple](https://support.apple.com/en-us/102774).
- Si hay un cambio en el mes o en los nombres, envía una notificación a los usuarios.

### 2. Monitoreo de Firmwares

- Consulta [https://api.ipsw.me](https://ipsw.me/) para obtener la última versión de firmware por dispositivo.
- Si detecta una nueva versión, busca detalles de seguridad en la página oficial de Apple.
- Informa automáticamente a todos los usuarios suscritos.

## ⚙️ Instalación

1. Clona el repositorio:
   ```bash
   git clone https://github.com/AldazActivator/Apple-Updates-bot.git
   cd Apple-Updates-bot
   ```

2. Configura tu token de Telegram:
   Abre el archivo PHP y reemplaza:
   ```php
   $bot = new AppleBot("TU_TOKEN_DE_TELEGRAM");
   ```

3. Coloca los siguientes archivos de datos vacíos en la misma carpeta:
   ```bash
   touch users.json latest_ack.json latest_firmware.json telegram_log.txt
   ```

4. Configura tu servidor con soporte para Webhooks o ejecuta con un cronjob:

   ### Webhook (Telegram)
   - Apunta el webhook de tu bot a:
     ```
     https://tudominio.com/bot_ios.php
     ```
   - Telegram enviará automáticamente actualizaciones cuando un usuario escriba `/start`.

   ### Cronjob Manual
   - Puedes ejecutar el chequeo programado con:
     ```bash
     php bot_ios.php check
     ```
   - O vía HTTP GET:
     ```
     https://tudominio.com/bot_ios.php?status=check
     ```

## 📂 Estructura de Archivos

| Archivo | Descripción |
|--------|-------------|
| `bot_ios.php` | Script principal del bot |
| `users.json` | Lista de chat IDs de usuarios suscritos |
| `latest_ack.json` | Último mes y nombres reconocidos por Apple |
| `latest_firmware.json` | Últimas versiones conocidas de firmware |
| `telegram_log.txt` | Registro de mensajes enviados y errores |

## 🛡️ Seguridad y Tolerancia a Errores

- Manejo de errores mediante excepciones y logging.
- Compatible con UTF-8 para evitar problemas de codificación.
- Corte de mensajes largos para evitar errores de Telegram (> 4096 caracteres).

## 👨‍💻 Autor

Desarrollado por [Gerson Aldaz](https://github.com/AldazActivator)  
Bot Token y estructura lista para personalizar según tu proyecto.

## 📝 Licencia

Este proyecto es de código abierto bajo licencia [MIT](LICENSE).
