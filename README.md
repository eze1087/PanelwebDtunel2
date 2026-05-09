# DTunnelMod Panel - By El NeNe - Clone Completo

Panel de gerenciamento para o aplicativo DTunnelMod, desenvolvido em PHP 7 + SQLite.

## Requisitos

- PHP 7.4 ou superior
- Extensão PDO SQLite habilitada
- Apache com mod_rewrite habilitado (ou Nginx com configuração equivalente)

## Estrutura do Projeto

```
dtunnel_clone/
├── index.php              # Roteador principal (ponto de entrada)
├── .htaccess              # Regras de reescrita de URL
├── db.php                 # Conexão SQLite e criação de tabelas
├── auth.php               # Sistema de autenticação
├── api/
│   ├── index.php          # API interna (AJAX do painel)
│   └── dtunnel.php        # API pública para o aplicativo DTunnelMod
├── pages/
│   ├── login.php          # Página de login
│   ├── register.php       # Página de registro
│   ├── home.php           # Panel principal
│   ├── aplicativo.php     # Página do aplicativo
│   ├── categorias.php     # Gestionar categorias
│   ├── cdn.php            # Gestionar CDNs
│   ├── configuracoes.php  # Gestionar configurações do app
│   ├── textos.php         # Editar textos do app
│   ├── usuarios.php       # Gestionar usuários
│   └── perfil.php         # Perfil do usuário
├── includes/
│   ├── layout.php         # Layout base HTML
│   ├── sidebar.php        # Sidebar/menu lateral
│   └── header.php         # Header/topbar
├── assets/
│   ├── css/style.css      # Estilos principais
│   ├── js/main.js         # JavaScript principal
│   └── svg/favicon.svg    # Ícone do site
└── database/
    └── database.sqlite    # Banco de dados (criado automaticamente)
```

## Instalação

### Apache

1. Faça upload de todos os arquivos para o diretório raiz do seu domínio (ex: `public_html/`)
2. Certifique-se que o `mod_rewrite` está habilitado
3. Acesse o domínio - o banco de dados será criado automaticamente

### Nginx

Adicione ao seu `server {}`:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}

# Bloquear acesso direto às pastas
location ~ ^/(pages|includes|database|api)/ {
    deny all;
}
```

## Primeiro Acesso

1. Acesse `/register` para criar sua conta
2. Você receberá **4 dias de acesso gratuito** automaticamente
3. Faça login em `/login`

## API Pública (Para o Aplicación)

### Configuraciones do App
```
GET /api/dtunnel.php?token=SEU_TOKEN&action=config
```

### Textos do App
```
GET /api/dtunnel.php?token=SEU_TOKEN&action=text
```

### Versión
```
GET /api/dtunnel.php?action=version
```

## Rotas do Panel

| Rota | Descripción |
|------|-----------|
| `/login` | Página de login |
| `/register` | Página de registro |
| `/home` | Panel principal |
| `/aplicativo` | Gestionar aplicativo |
| `/configuracoes` | Configuraciones do app |
| `/categorias` | Categorías |
| `/cdn` | CDNs |
| `/textos` | Textos do app |
| `/usuarios` | Gestionar usuários |
| `/perfil` | Perfil do usuário |
| `/logout` | Salir |

## Funcionalidades

- ✅ Sistema de login e registro
- ✅ Proteção de rotas (sem acesso direto a arquivos PHP)
- ✅ URLs amigáveis (sem extensão .php)
- ✅ Tema claro e escuro
- ✅ Sistema de idiomas (PT, EN, ES)
- ✅ Panel principal com estatísticas
- ✅ Gestionar configurações do app (SSH, V2Ray, OpenVPN, DNSTT, Hysteria)
- ✅ Gestionar categorias com cores
- ✅ Gestionar CDNs
- ✅ Editar textos do aplicativo
- ✅ Gestionar usuários (criar, editar, bloquear, excluir)
- ✅ Modal de bloqueio para usuários bloqueados
- ✅ Sistema de token para autenticação do app
- ✅ API pública compatível com DTunnelMod
- ✅ Exportar/importar configurações em JSON
- ✅ Notificaciones toast
- ✅ Modais de confirmação
- ✅ Responsivo para todos os dispositivos
- ✅ Banco de dados SQLite (sem necessidade de MySQL)

## Segurança

- Contraseñas criptografadas com bcrypt
- Proteção CSRF via session
- Acesso direto a arquivos PHP bloqueado via .htaccess
- Usuarios bloqueados recebem modal de bloqueio imediatamente
- Tokens únicos por usuário para autenticação do app

## Compatibilidade

- PHP 7.4+
- SQLite 3
- Funciona em hospedagens compartilhadas com PHP
