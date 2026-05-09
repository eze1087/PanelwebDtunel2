#!/bin/bash
# ============================================================
#   By Elnene Panel WEB2 - Instalador By El NeNe
#   WA: 3455236886 | TG: @El_NeNe_Sando
# ============================================================
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'
BCYAN='\033[1;36m'; BRED='\033[1;31m'; DIM='\033[2m'
REPO="https://github.com/eze1087/PanelwebDtunel2"
INSTALL_DIR="/var/www/html"
MENU_CMD="panel"

PANEL_VERSION="v1.0.55"
PANEL_COMMIT="sign-button-fixes"
PANEL_DATE="2026-05-11"

echo ""
echo -e "${BCYAN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BCYAN}║${NC}               ${BRED}★  PANEL WEB2  By El NeNe  ★${NC}               ${BCYAN}║${NC}"
echo -e "${BCYAN}║${NC}         ${DIM}${PANEL_VERSION}  *  commit: ${PANEL_COMMIT}  *  ${PANEL_DATE}         ${NC}${BCYAN}║${NC}"
echo -e "${BCYAN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""

[ "$EUID" -ne 0 ] && echo -e "${RED}[ERROR] Ejecutar como root: sudo bash install.sh${NC}" && exit 1

echo -e "${YELLOW}[1/8] Actualizando sistema...${NC}"
apt update -y -qq

echo -e "${YELLOW}[2/8] Instalando dependencias...${NC}"
apt install -y -qq apache2 php php-sqlite3 php-mbstring php-curl php-json php-zip git curl unzip sqlite3 python3 openjdk-21-jdk

echo -e "${YELLOW}[3/8] Configurando Apache...${NC}"
a2enmod rewrite -q
cat > /etc/apache2/sites-available/000-default.conf << 'APACHECONF'
<VirtualHost *:80>
    ServerName panelweb2.elnene.site
    ServerAlias www.panelweb2.elnene.site 149.50.134.137
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    # Timeout extendido para compilación de APKs grandes (60MB+)
    Timeout 1800
    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
APACHECONF
sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf 2>/dev/null

echo -e "${YELLOW}[4/8] Descargando panel desde GitHub...${NC}"
rm -rf /tmp/dtpanel_install
git clone "$REPO" /tmp/dtpanel_install --quiet 2>&1
[ ! -d "/tmp/dtpanel_install" ] && echo -e "${RED}[ERROR] No se pudo descargar. El repo debe ser público.${NC}" && exit 1

echo -e "${YELLOW}[5/8] Instalando archivos...${NC}"
find "$INSTALL_DIR" -mindepth 1 -delete 2>/dev/null || true
cp -r /tmp/dtpanel_install/. "$INSTALL_DIR/"
rm -rf /tmp/dtpanel_install

echo -e "${YELLOW}[6/8] Creando .htaccess y estructura...${NC}"
cat > "$INSTALL_DIR/.htaccess" << 'HTEOF'
Options -Indexes
RewriteEngine On
RewriteBase /
RewriteRule ^(pages|includes|database|api|db)/.*$ /404 [R=403,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Límites PHP para compilación de APKs grandes (60MB+)
# Funciona con mod_php — complementa el .user.ini de PHP-FPM
<IfModule mod_php.c>
    php_value memory_limit 1024M
    php_value max_execution_time 0
    php_value max_input_time 600
    php_value post_max_size 210M
    php_value upload_max_filesize 200M
</IfModule>
<IfModule mod_php8.c>
    php_value memory_limit 1024M
    php_value max_execution_time 0
    php_value max_input_time 600
    php_value post_max_size 210M
    php_value upload_max_filesize 200M
</IfModule>
<IfModule mod_php7.c>
    php_value memory_limit 1024M
    php_value max_execution_time 0
    php_value max_input_time 600
    php_value post_max_size 210M
    php_value upload_max_filesize 200M
</IfModule>
HTEOF

mkdir -p "$INSTALL_DIR/database" "$INSTALL_DIR/db"
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 644 "$INSTALL_DIR/.htaccess"
chmod -R 775 "$INSTALL_DIR/database" "$INSTALL_DIR/db"

# Crear carpetas APK con permisos correctos
mkdir -p "$INSTALL_DIR/apk_base" "$INSTALL_DIR/downloads"
chmod 775 "$INSTALL_DIR/apk_base" "$INSTALL_DIR/downloads"
chown www-data:www-data "$INSTALL_DIR/apk_base" "$INSTALL_DIR/downloads"

# Configurar límites PHP para subida de APKs (funciona con PHP-FPM y mod_php)
cat > "$INSTALL_DIR/.user.ini" << 'PHPINI'
upload_max_filesize = 200M
post_max_size = 210M
max_execution_time = 0
max_input_time = 600
memory_limit = 1024M
PHPINI
chmod 644 "$INSTALL_DIR/.user.ini"

# Aplicar también en php.ini de FPM si existe
for PHP_INI in /etc/php/*/fpm/php.ini /etc/php/*/apache2/php.ini; do
    if [ -f "$PHP_INI" ]; then
        sed -i 's/upload_max_filesize = .*/upload_max_filesize = 200M/' "$PHP_INI" 2>/dev/null
        sed -i 's/post_max_size = .*/post_max_size = 210M/' "$PHP_INI" 2>/dev/null
        sed -i 's/max_execution_time = .*/max_execution_time = 0/' "$PHP_INI" 2>/dev/null
        sed -i 's/memory_limit = .*/memory_limit = 1024M/' "$PHP_INI" 2>/dev/null
    fi
done

# Aplicar también en cada pool de PHP-FPM (www.conf)
for POOL_CONF in /etc/php/*/fpm/pool.d/www.conf; do
    if [ -f "$POOL_CONF" ]; then
        # Agregar o actualizar los límites en el pool
        grep -q "php_admin_value\[upload_max_filesize\]" "$POOL_CONF" \
            && sed -i 's/php_admin_value\[upload_max_filesize\].*/php_admin_value[upload_max_filesize] = 200M/' "$POOL_CONF" \
            || echo "php_admin_value[upload_max_filesize] = 200M" >> "$POOL_CONF"
        grep -q "php_admin_value\[post_max_size\]" "$POOL_CONF" \
            && sed -i 's/php_admin_value\[post_max_size\].*/php_admin_value[post_max_size] = 210M/' "$POOL_CONF" \
            || echo "php_admin_value[post_max_size] = 210M" >> "$POOL_CONF"
        grep -q "php_admin_value\[memory_limit\]" "$POOL_CONF" \
            && sed -i 's/php_admin_value\[memory_limit\].*/php_admin_value[memory_limit] = 512M/' "$POOL_CONF" \
            || echo "php_admin_value[memory_limit] = 1024M" >> "$POOL_CONF"
    fi
done

# Reiniciar PHP-FPM para aplicar límites
systemctl restart "php*-fpm" 2>/dev/null || true
for FPM in $(systemctl list-units --type=service --state=active 2>/dev/null | grep php | grep fpm | awk '{print $1}'); do
    systemctl restart "$FPM" 2>/dev/null || true
done

# Corrección preventiva: por si alguna función PHP fue traducida incorrectamente
find "$INSTALL_DIR" -name "*.php" -exec sed -i   -e 's/session_estado()/session_status()/g'   -e 's/session_iniciar()/session_start()/g'   -e 's/session_destruir()/session_destroy()/g'   -e 's/session_desconfigurar()/session_unset()/g'   {} \; 2>/dev/null


echo -e "${YELLOW}[6b/8] Configurando firma automática de APKs...${NC}"
SIGN_DIR="$INSTALL_DIR/panel/sign"
mkdir -p "$SIGN_DIR"
chown www-data:www-data "$SIGN_DIR" 2>/dev/null || true
chmod 750 "$SIGN_DIR"

KEY_ALIAS="dtunnelkey"
KEY_PASS="Dtunnel2024!!"
KEYSTORE="$SIGN_DIR/release.jks"

# Generar keystore si no existe
if [ ! -f "$KEYSTORE" ]; then
    KEYTOOL=$(command -v keytool 2>/dev/null \
           || find /usr/lib/jvm -name "keytool" 2>/dev/null | head -1)
    if [ -n "$KEYTOOL" ]; then
        "$KEYTOOL" -genkey -v -noprompt \
            -keystore "$KEYSTORE" -alias "$KEY_ALIAS" \
            -keyalg RSA -keysize 2048 -validity 10000 \
            -storepass "$KEY_PASS" -keypass "$KEY_PASS" \
            -dname "CN=DTunnel, OU=DTunnel, O=DTunnel, L=BuenosAires, S=BA, C=AR" \
            2>/dev/null \
            && echo -e "  ${GREEN}✓ Keystore de firma generado${NC}" \
            || echo -e "  ${YELLOW}⚠ Keystore se generará en la primera compilación${NC}"
    fi
fi

# Descargar uber-apk-signer.jar (V1+V2+V3 + zipalign integrado, con main-class válido)
UBER_SIGNER="$SIGN_DIR/uber-apk-signer.jar"
if [ ! -f "$UBER_SIGNER" ]; then
    UBER_URL="https://github.com/patrickfav/uber-apk-signer/releases/download/v1.3.0/uber-apk-signer-1.3.0.jar"
    if wget -q --timeout=60 -O "$UBER_SIGNER" "$UBER_URL" 2>/dev/null && [ -s "$UBER_SIGNER" ]; then
        echo -e "  ${GREEN}✓ uber-apk-signer.jar descargado (firma V1+V2+V3)${NC}"
    else
        echo -e "  ${YELLOW}⚠ uber-apk-signer no descargado — fallback: jarsigner V1${NC}"
        rm -f "$UBER_SIGNER" 2>/dev/null
    fi
fi

chown -R www-data:www-data "$SIGN_DIR" 2>/dev/null || true

echo -e "${YELLOW}[7/8] Creando usuario administrador...${NC}"
ADMIN_EMAIL="elnene.admin@gmail.com"
ADMIN_PASS="admin2004"
ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
NOW=$(date '+%Y-%m-%d %H:%M:%S')
ADMIN_UUID=$(python3 -c "import uuid; print(str(uuid.uuid4()))")
python3 -c "
import json
admin = [{
    'uuid': '$ADMIN_UUID',
    'username': 'ElNeNe',
    'email': '$ADMIN_EMAIL',
    'password': '$ADMIN_HASH',
    'role': 'admin',
    'status': 'active',
    'created_at': '$NOW',
    'expires_at': '2099-12-31 23:59:59',
    'is_blocked': False,
    'avatar_url': ''
}]
open('$INSTALL_DIR/db/usuarios.json','w').write(json.dumps(admin, indent=4, ensure_ascii=False))
"
chown -R www-data:www-data "$INSTALL_DIR/db"

echo -e "${YELLOW}[8/8] Instalando comando dtpanel y configurando servicios...${NC}"
systemctl enable apache2 -q
systemctl restart apache2

# ── Instalar comando dtpanel ─────────────────────────────────────────────
# ── Instalar comando dtpanel desde el panel instalado ────────────────────
cp "$INSTALL_DIR/dtpanel" /usr/local/bin/$MENU_CMD

chmod +x /usr/local/bin/$MENU_CMD

SERVER_IP=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || curl -4 -s --max-time 5 icanhazip.com 2>/dev/null || hostname -I | tr ' ' '\n' | grep -v ':' | head -1)

echo ""
echo -e "${BCYAN}╔══════════════════════════════════════════════════════════╗${NC}"
echo -e "${BCYAN}║${NC}            ${GREEN}INSTALACION COMPLETADA  [ OK ]${NC}             ${BCYAN}║${NC}"
echo -e "${BCYAN}║${NC}         ${DIM}${PANEL_VERSION}  *  commit: ${PANEL_COMMIT}  *  ${PANEL_DATE}         ${NC}${BCYAN}║${NC}"
echo -e "${BCYAN}╚══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${CYAN}Panel web:${NC} ${GREEN}http://${SERVER_IP}${NC}"
echo -e "  ${CYAN}Registro:${NC}  ${GREEN}http://${SERVER_IP}/register${NC}"
echo -e "  ${CYAN}Login:${NC}     ${GREEN}http://${SERVER_IP}/login${NC}"
echo ""
echo -e "  ${BOLD}Credenciales admin:${NC}"
echo -e "  Email: ${YELLOW}elnene.admin@gmail.com${NC}"
echo -e "  Pass:  ${YELLOW}admin2004${NC}  <- ¡Cambiala con: dtpanel -> opción 9!"
echo ""
echo -e "  ${CYAN}Gestión:${NC} escribí ${YELLOW}panel${NC} en cualquier momento"
echo ""
echo -e "  ${CYAN}Soporte:${NC}"
echo -e "  WhatsApp: ${GREEN}+54 3455-236886${NC}"
echo -e "  Telegram: ${GREEN}@El_NeNe_Sando${NC}"
echo ""
