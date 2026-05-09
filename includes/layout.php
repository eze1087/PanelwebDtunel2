<?php
if (!defined('DTUNNEL_APP')) { header('HTTP/1.0 403 Forbidden'); exit; }
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle ?? 'By Elnene Panel WEB2') ?> - By Elnene Panel WEB2</title>
    <link rel="icon" href="/assets/svg/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if (isset($extraCss)) echo $extraCss; ?>
    <script>
        // Carrega o tema imediatamente para evitar tela piscando branca
        (function() {
            const theme = localStorage.getItem('dtunnel-theme') || 'light';
            if (theme === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
</head>
<body>
    
    <!-- OVERLAY DA BARRA LATERAL (Independente e Seguro) -->
    <div class="sidebar-overlay" id="globalSidebarOverlay" onclick="forceCloseSidebar()" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:998;opacity:0;visibility:hidden;transition:all 0.3s ease;pointer-events:none;"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/header.php'; ?>
            
            <main class="page-content">
                <?php echo $pageContent ?? ''; ?>
            </main>
        </div>
    </div>

    <!-- MODAL GLOBAL DE EXCLUSÃO (Realista e Top) -->
    <div id="global-confirm-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);-webkit-tap-highlight-color: transparent;">
        <div id="global-confirm-box" style="background:var(--card-bg);border:1px solid var(--card-border);border-radius:24px;width:90%;max-width:400px;padding:24px;transform:scale(0.95) translateY(10px);transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);box-shadow:0 20px 40px rgba(0,0,0,0.2);">
            
            <h3 id="g-confirm-title" style="margin:0 0 12px 0;font-size:1.25rem;color:var(--text-main);font-weight:800;">Remover conta</h3>
            
            <div id="g-confirm-msg" style="color:var(--text-muted);font-size:0.95rem;line-height:1.5;">
                <!-- Preenchido via JS -->
            </div>
            
            <div style="display:flex;gap:12px;margin-top:28px;justify-content:flex-end;">
                <button onclick="closeGlobalModal()" style="padding:12px 20px;border-radius:14px;background:transparent;border:1px solid var(--card-border);color:var(--text-main);font-weight:600;cursor:pointer;flex:1;transition:background 0.2s;outline:none;">
                    Cancelar
                </button>
                <button id="g-confirm-btn" style="padding:12px 20px;border-radius:14px;background:#ef4444;border:none;color:#fff;font-weight:600;cursor:pointer;flex:1;transition:transform 0.15s, opacity 0.2s;outline:none;">
                    Eliminar
                </button>
            </div>
        </div>
    </div>

    <script src="/assets/js/main.js"></script>
    
    <script>
    // =====================================================================
    // SOLUÇÃO DEFINITIVA DO BUG DA BARRA LATERAL (NUNCA MAIS TRAVA)
    // =====================================================================
    window.forceOpenSidebar = function() {
        const sidebar = document.querySelector('.sidebar') || document.getElementById('sidebar');
        const overlay = document.getElementById('globalSidebarOverlay');
        
        if (sidebar) {
            // Força a remoção da classe closed e aplica CSS inline caso haja conflito de tema
            sidebar.classList.remove('closed');
            sidebar.style.transform = 'translateX(0)';
        }
        if (overlay) {
            overlay.style.opacity = '1';
            overlay.style.visibility = 'visible';
            overlay.style.pointerEvents = 'auto'; // Permite clicar fora para fechar
        }
    };

    window.forceCloseSidebar = function() {
        const sidebar = document.querySelector('.sidebar') || document.getElementById('sidebar');
        const overlay = document.getElementById('globalSidebarOverlay');
        
        if (sidebar) {
            sidebar.classList.add('closed');
            sidebar.style.transform = 'translateX(-100%)';
        }
        if (overlay) {
            overlay.style.opacity = '0';
            overlay.style.visibility = 'hidden';
            overlay.style.pointerEvents = 'none'; // Desabilita cliques fantasmas
        }
    };

    // =====================================================================
    // SISTEMA DO MODAL LINDO DE REMOVER CUENTAS SALVAS
    // =====================================================================
    window.openDeleteModal = function(id, name) {
        const modal = document.getElementById('global-confirm-modal');
        const box = document.getElementById('global-confirm-box');
        const msg = document.getElementById('g-confirm-msg');
        const btn = document.getElementById('g-confirm-btn');
        
        // Puxa as traduções globais (Se existirem no header)
        const lang = localStorage.getItem('app_language') || 'pt';
        const t = (window.globalTranslations && window.globalTranslations[lang]) ? window.globalTranslations[lang] : window.globalTranslations['pt'];

        document.getElementById('g-confirm-title').innerText = t.confirm_delete_title;
        
        // Caixa top de alerta vermelha embutida na mensagem
        msg.innerHTML = `
            ${t.confirm_delete_msg} <b style="color:var(--text-main);">${name}</b>?
            <div style="background:rgba(239, 68, 68, 0.08); border-left:4px solid #ef4444; padding:12px; border-radius:0 8px 8px 0; margin-top:16px; color:var(--text-main); font-size:0.85rem; font-weight:600;">
                ${t.warning_undo}
            </div>
        `;

        // Traduz Botões
        const cancelBtn = box.querySelector('button[onclick="closeGlobalModal()"]');
        cancelBtn.innerText = t.cancel;
        btn.innerText = t.delete;

        // Ação de Eliminar com animação antes de redirecionar
        btn.onclick = function() {
            this.style.transform = 'scale(0.95)';
            this.style.opacity = '0.8';
            
            // Aqui ele redireciona para a página PHP que deleta no SQLite e volta
            setTimeout(() => {
                window.location.href = `/remover-conta.php?id=${id}`;
            }, 150);
        };

        // Mostra o Modal com suavidade
        modal.style.opacity = '1';
        modal.style.visibility = 'visible';
        box.style.transform = 'scale(1) translateY(0)';
    };

    window.closeGlobalModal = function() {
        const modal = document.getElementById('global-confirm-modal');
        const box = document.getElementById('global-confirm-box');
        
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        box.style.transform = 'scale(0.95) translateY(10px)';
    };
    </script>
    
    <?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>