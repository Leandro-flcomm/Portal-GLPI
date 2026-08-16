#!/bin/bash
# ============================================================
# Comandos de instalação do plugin flcportal no GLPI 11
# Execute como root ou com sudo no servidor GLPI
# ============================================================

GLPI_ROOT="/var/www/html/glpi"
WEB_USER="www-data"

# 1. Copiar plugin
cp -r flcportal "$GLPI_ROOT/plugins/"
chown -R "$WEB_USER:$WEB_USER" "$GLPI_ROOT/plugins/flcportal"

# 2. Criar symlink para assets estáticos
ln -s "$GLPI_ROOT/plugins" "$GLPI_ROOT/public/plugins"

# 3. Atualizar .htaccess (faça backup antes!)
cp "$GLPI_ROOT/public/.htaccess" "$GLPI_ROOT/public/.htaccess.bak"
# Cole o conteúdo do arquivo docs/htaccess.txt em $GLPI_ROOT/public/.htaccess

# 4. Instalar e ativar o plugin
cd "$GLPI_ROOT"
sudo -u "$WEB_USER" php bin/console plugin:install flcportal
sudo -u "$WEB_USER" php bin/console plugin:activate flcportal

# 5. Limpar cache
sudo -u "$WEB_USER" php bin/console cache:clear

# 6. Configurar perfil Self-Service como helpdesk
mysql -u root glpi -e "UPDATE glpi_profiles SET interface='helpdesk' WHERE name='Self-Service';"

# 7. Recarregar Apache
apache2ctl graceful

echo "Instalação concluída!"
