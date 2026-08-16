# FLComm Self-Service Portal — Plugin GLPI

**Plugin para GLPI 11** que substitui automaticamente a interface padrão de autoatendimento por um portal moderno com sidebar de navegação, catálogo de serviços e acompanhamento de chamados.

---

## Sumário

- [O que faz](#o-que-faz)
- [Requisitos](#requisitos)
- [Estrutura de arquivos](#estrutura-de-arquivos)
- [Instalação passo a passo](#instalação-passo-a-passo)
- [Configuração pós-instalação](#configuração-pós-instalação)
- [Temas por entidade](#temas-por-entidade)
- [Como funciona internamente](#como-funciona-internamente)
- [Solução de problemas](#solução-de-problemas)
- [Desinstalação](#desinstalação)

---

## O que faz

Usuários com perfil **Self-Service** (interface `helpdesk`) são redirecionados automaticamente para o portal ao fazer login. O portal oferece:

- **Sidebar** com logo da entidade, navegação e informações do usuário
- **Catálogo de Serviços** — categorias de atendimento configuradas no GLPI
- **Abertura de chamados** via formulário simples
- **Meus Chamados** — lista dos chamados abertos pelo usuário
- **Tema por entidade** — cores da sidebar e botões mudam conforme a entidade ativa do usuário

---

## Requisitos

| Item | Versão/Detalhe |
|------|----------------|
| GLPI | 11.0.x |
| PHP | 8.2+ |
| Servidor web | Apache 2.4+ com `mod_rewrite` |
| Permissões | Apache precisa de `FollowSymLinks` no DocumentRoot |

> **Atenção:** Não compatível com GLPI 10.x ou anterior.

---

## Estrutura de arquivos

```
flcportal/
├── setup.php               # Registro do plugin e hook post_init
├── hook.php                # Lógica de redirecionamento automático
├── css/
│   └── portal.css          # Estilos completos do portal
├── front/
│   └── portal.php          # Página principal do portal (roteamento interno)
├── inc/
│   └── portal.class.php    # Classe de dados (tickets, catálogo, temas)
└── js/
    └── portal.js           # JavaScript (auto-dismiss alertas, etc.)
```

---

## Instalação passo a passo

### 1. Copiar os arquivos do plugin

Copie a pasta `flcportal` inteira para o diretório de plugins do GLPI:

```bash
cp -r flcportal /var/www/html/glpi/plugins/
chown -R www-data:www-data /var/www/html/glpi/plugins/flcportal
```

### 2. Criar o symlink para servir CSS e JS

O GLPI 11 usa `public/` como DocumentRoot. Para que o Apache sirva os arquivos estáticos do plugin (CSS, JS, imagens), é necessário criar um symlink:

```bash
ln -s /var/www/html/glpi/plugins /var/www/html/glpi/public/plugins
```

Verifique que o vhost do Apache tem `FollowSymLinks`:

```apache
<Directory /var/www/html/glpi/public>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 3. Atualizar o `.htaccess` do GLPI

Edite `/var/www/html/glpi/public/.htaccess` para garantir que arquivos PHP de plugins continuem passando pelo Symfony (e não sejam executados diretamente), enquanto CSS/JS são servidos como arquivos estáticos:

```apache
RewriteEngine On

# Plugin PHP files must go through Symfony even if they exist via symlink
RewriteRule ^plugins/.+\.php$ index.php [QSA,L]

# Redirect all non-existing files/dirs to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options SAMEORIGIN
</IfModule>
```

### 4. Instalar o plugin via CLI do GLPI

```bash
cd /var/www/html/glpi
sudo -u www-data php bin/console plugin:install flcportal
sudo -u www-data php bin/console plugin:activate flcportal
```

Saída esperada:
```
Plugin flcportal installed.
Plugin flcportal activated.
```

### 5. Limpar o cache do Symfony

```bash
sudo -u www-data php bin/console cache:clear
```

### 6. Configurar o perfil Self-Service para interface helpdesk

O plugin intercepta apenas usuários com `interface = helpdesk`. No GLPI padrão esse campo pode estar como `central` — corrija via SQL:

```sql
UPDATE glpi_profiles SET interface = 'helpdesk' WHERE name = 'Self-Service';
```

Ou diretamente no banco (via MySQL/MariaDB):

```bash
mysql -u root glpi -e "UPDATE glpi_profiles SET interface='helpdesk' WHERE name='Self-Service';"
```

> Se você tiver perfis customizados de autoatendimento (ex.: `flcomm-ticket`), repita o UPDATE para cada um.

### 7. Recarregar o Apache

```bash
apache2ctl graceful
```

---

## Configuração pós-instalação

### Configurar Catálogo de Serviços

O portal exibe as **categorias de ITIL** marcadas como visíveis ao helpdesk. Para configurar:

1. Acesse **Configuração → ITIL → Categorias**
2. Crie ou edite categorias
3. Marque a opção **"Visível no helpdesk"**
4. Defina a entidade correta para cada categoria

### Adicionar logos das entidades

O plugin busca logos em `/var/www/html/glpi/public/pics/logos/`:

| Entidade ID | Arquivo esperado |
|-------------|-----------------|
| 0 (FLComm) | `flcomm_logo.png` |
| 1 (Asso Marítima) | `asso_logo.png` |
| 2 (Camorim) | `camorim_logo.png` |

```bash
cp seu_logo.png /var/www/html/glpi/public/pics/logos/flcomm_logo.png
chown www-data:www-data /var/www/html/glpi/public/pics/logos/*.png
```

### Atribuir perfil Self-Service aos usuários

Usuários que devem usar o portal precisam ter o perfil com `interface = helpdesk` atribuído:

1. Acesse **Administração → Usuários**
2. Edite o usuário
3. Na aba **Perfis**, adicione o perfil Self-Service na entidade correta

---

## Temas por entidade

O tema (cores da sidebar e botões) é definido automaticamente pela **entidade ativa** do usuário ao fazer login. Os temas são configurados em `inc/portal.class.php`:

```php
private const ENTITY_THEMES = [
    0 => ['sidebar' => '#0a2347', 'accent' => '#2563EB', 'name' => 'FLComm'],
    1 => ['sidebar' => '#1a3a5c', 'accent' => '#1a56b0', 'name' => 'Asso Marítima'],
    2 => ['sidebar' => '#7f1d1d', 'accent' => '#c0392b', 'name' => 'Camorim'],
];
```

Para adicionar uma nova entidade, adicione uma entrada com o ID da entidade no GLPI e as cores desejadas. Os logos seguem o mesmo padrão em `ENTITY_LOGOS`.

---

## Como funciona internamente

### Fluxo de redirecionamento

```
Usuário faz login
        ↓
GLPI dispara hook post_init (hook.php)
        ↓
Verifica: usuário logado? interface = helpdesk?
        ↓ sim
URI contém bypass? (/ajax/, /api, flcportal, logout...)
        ↓ não
header('Location: /plugins/flcportal/front/portal.php')
exit()
        ↓
Portal renderiza (front/portal.php)
```

### Por que `header()` em vez de `Html::redirect()`?

No GLPI 11, `Html::redirect()` lança uma `RedirectException` que é tratada pelo Symfony dentro de `HttpKernel::handle()`. Porém, o hook `post_init` é disparado durante `Kernel::boot()`, que é executado **antes** do `HttpKernel::handle()`. A exceção não é capturada pelo handler correto e resulta em erro 500.

Usar `header()` + `exit()` diretamente funciona porque, nesse ponto do ciclo de vida, nenhuma saída HTTP foi enviada ainda.

### Por que o symlink + regra no `.htaccess`?

O GLPI 11 tem `public/` como DocumentRoot. Arquivos em `plugins/` não são acessíveis diretamente. O symlink `public/plugins → ../plugins` resolve o acesso a CSS/JS. A RewriteRule adicional garante que arquivos PHP de plugins ainda passem pelo Symfony (para que o bootstrap do GLPI funcione corretamente), enquanto arquivos estáticos (CSS, JS, imagens) são servidos diretamente pelo Apache.

---

## Solução de problemas

### Portal não redireciona após login

1. Verifique se o perfil do usuário tem `interface = helpdesk`:
   ```sql
   SELECT name, interface FROM glpi_profiles;
   ```

2. Verifique se o plugin está ativo:
   ```sql
   SELECT directory, name, state FROM glpi_plugins WHERE directory = 'flcportal';
   -- state deve ser 1 (ACTIVATED)
   ```

3. Verifique os logs do Apache:
   ```bash
   grep flcportal /glpi_error.log | tail -20
   ```
   Deve aparecer `[flcportal] post_init: redirecting to ...`

### Portal carrega sem CSS

1. Verifique se o symlink existe:
   ```bash
   ls -la /var/www/html/glpi/public/plugins
   # deve mostrar: plugins -> /var/www/html/glpi/plugins
   ```

2. Verifique se o `.htaccess` tem a RewriteRule para plugins:
   ```bash
   cat /var/www/html/glpi/public/.htaccess
   ```

3. Tente acessar diretamente: `https://seu-glpi/plugins/flcportal/css/portal.css`
   - Se retornar 404: symlink não está funcionando
   - Se retornar o CSS: verifique o `<link>` no HTML gerado

### Erro 500 ao acessar o portal

Verifique o log:
```bash
grep -E "PHP (Fatal|Warning|Error)" /glpi_error.log | tail -20
```

Causas comuns:
- Função GLPI não encontrada → plugin não está carregado
- Sessão inválida → limpe cookies e faça login novamente
- Cache desatualizado → `php bin/console cache:clear`

### Loop infinito de redirecionamento

O plugin tem uma lista de bypass que evita redirecionar rotas essenciais. Se ocorrer loop, verifique se a URL do portal contém `flcportal` (ela deve conter):
```
/plugins/flcportal/front/portal.php  ✓ contém "flcportal"
```

Se a URL do portal for customizada e não contiver `flcportal`, adicione-a ao bypass em `hook.php`.

---

## Desinstalação

```bash
# 1. Desativar e desinstalar via CLI
cd /var/www/html/glpi
sudo -u www-data php bin/console plugin:deactivate flcportal
sudo -u www-data php bin/console plugin:uninstall flcportal

# 2. Remover arquivos do plugin
rm -rf /var/www/html/glpi/plugins/flcportal

# 3. Remover symlink
rm /var/www/html/glpi/public/plugins

# 4. Restaurar .htaccess original
# Remova a linha: RewriteRule ^plugins/.+\.php$ index.php [QSA,L]

# 5. Reverter perfil Self-Service (se necessário)
mysql -u root glpi -e "UPDATE glpi_profiles SET interface='central' WHERE name='Self-Service';"

# 6. Limpar cache
sudo -u www-data php bin/console cache:clear
```

---

## Desenvolvido por

**FLComm TI** — Sistema interno de autoatendimento para GLPI 11.

- Entidades: FLComm, Asso Marítima, Camorim
- Versão do plugin: 1.0.0
- Compatibilidade GLPI: 11.0.0 – 12.0.0
